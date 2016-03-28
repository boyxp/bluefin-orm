<?php
declare(strict_types=1);
namespace bluefin\orm\query\adapter;
use MongoDB\Driver\Command;
use MongoDB\BSON\ObjectID;
use MongoDB\Driver\BulkWrite;
use MongoDB\Driver\Query as MongoQuery;

use bluefin\orm\model\model;
use bluefin\orm\record\record;
use bluefin\orm\resultset\resultset;
use bluefin\orm\query\query as queryInterface;
class mongodb extends \injector implements queryInterface
{
	private $_model     = null;
	private $_database  = null;
	private $_table     = null;

	private $_state     = 0;
	private $_type      = null;

	private $_columns   = null;
	private $_condition = '_id is not null';
	private $_bind      = [];
	private $_aggregate = null;
	private $_group     = '';
	private $_having    = '';
	private $_order     = [];
	private $_offset    = 0;
	private $_count     = 20;

	private $_data      = [];
	private $_record    = true;
	private $_registry  = null;

	const INSERT = 'INSERT';
	const SELECT = 'SELECT';
	const UPDATE = 'UPDATE';
	const DELETE = 'DELETE';

	public function __construct(model $model)
	{
		$this->_model    = $model;
		$this->_database = $model->getDatabase();
		$this->_table    = $model->getTable();
		$this->_registry = static::$_locator->make('registry', array('mongodb:SQL'));
	}

	public function insert(array $data):queryInterface
	{
		if($this->_state >= 1) { throw new \LogicException('syntax error'); }

		if(!isset($data['_id'])) {
			$data['_id'] = new ObjectID;
		}

		$this->_type = static::INSERT;
		$this->_data = $data;
		$this->_state= 7;

		return $this;
	}

	public function update(array $data):queryInterface
	{
		if($this->_state >= 1) { throw new \LogicException('syntax error'); }

		$this->_type = static::UPDATE;

		if(isset($data['_id'])) {
			$key = $data['_id'];
			unset($data['_id']);

			$this->_data = $data;

			return $this->where('_id=?', array($key));
		} else {
			$this->_data  = $data;
			$this->_state = 2;

			return $this;
		}
	}

	public function delete(array $data=null):queryInterface
	{
		if($this->_state >= 1) { throw new \LogicException('syntax error'); }

		$this->_type = static::DELETE;

		if(isset($data['_id'])) {
			return $this->where('_id=?', array($data['_id']));
		}

		$this->_state = 2;

		return $this;
	}

	public function select(string $columns='*'):queryInterface
	{
		if($this->_state >= 1) { throw new \LogicException('syntax error', 2001); }

		if(strpos($columns, '(')!==false and preg_match_all('/(?:,|^|\s)(avg|count|max|min|sum)\s*\(([^\(\)]+)\)\s*(?:as\s+([a-z0-9_]+))?/i', $columns, $matches)) {
			$aggregate = [];
			foreach($matches[1] as $key=>$function) {
				$field = $matches[2][$key];
				$as    = empty($matches[3][$key]) ? $function : $matches[3][$key];
				if($function==='count') {
					$aggregate[$as] = array('$sum'=>1);
				} else {
					$aggregate[$as] = array("\${$function}"=>"\${$field}");
				}
			}
			$this->_aggregate = $aggregate;

			$this->_record = false;
		} else {
			$this->_record = true;
			if($columns!=='*') {
				$fields = explode(',', $columns);
				$this->_columns = [];
				foreach($fields as $field) {
					$this->_columns[$field] = true;
				}
			}
		}

		$this->_type  = static::SELECT;
		$this->_state = 1;

		return $this;
	}

	public function from():queryInterface
	{
		if($this->_state >= 2) { throw new \LogicException('syntax error'); }

		$this->_state = 2;

		return $this;
	}

	public function where($condition, array $bind=null):queryInterface
	{
		if($this->_state >= 3) { throw new \LogicException('syntax error'); }

		$bind  = is_null($bind) ? [] : $bind;
		$where = static::_condition($condition, $bind);
		$this->_condition = $where['condition'];
		$this->_bind      = $where['bind'];
		$this->_state     = 3;

		return $this;
	}

	public function group(string $fields):queryInterface
	{
		if($this->_record or $this->_state >= 4) {
			throw new \LogicException('syntax error');
		}

		$group  = [];
		$fields = explode(',', $fields);
		foreach($fields as $field) {
			$group[$field] = '$'.$field;
		}

		$this->_group = $group;
		$this->_state = 4;

		return $this;
	}

	public function having(string $condition, array $bind=null):queryInterface
	{
		if($this->_state != 4) { throw new \LogicException('syntax error'); }

		$where = static::_condition($condition, $bind);
		$this->_having = $where['condition'];
		$this->_bind   = is_null($bind) ? $this->_bind : array_merge($this->_bind, $bind);
		$this->_state  = 5;

		return $this;
	}

	public function order(string $field, string $direction='ASC'):queryInterface
	{
		if($this->_state > 6) { throw new \LogicException('syntax error'); }

		$this->_order[$field] = strtolower($direction)==='asc' ? 1 : -1;
		$this->_state         = 6;

		return $this;
	}

	public function limit(int $rows, int $offset=0):queryInterface
	{
		if($this->_state >= 7) { throw new \LogicException('syntax error'); }

		$this->_offset = $offset;
		$this->_count  = $rows;
		$this->_state  = 7;

		return $this;
	}

	public function fetch($record=false)
	{
		$this->_offset = 0;
		$this->_count  = 1;

		$result = $this->fetchAll(false);
		if(!isset($result[0])) {
			return null;
		} elseif($record===false) {
			return $result[0];
		} elseif($this->_record===false) {
			return static::$_locator->make('record', [$result[0]]);
		} else {
			return static::$_locator->make('record', [$result[0], $this]);
		}
	}

	public function fetchAll($resultset=false)
	{
		$connection = $this->_model->getSlave();
		$manager    = $connection->getManager();
		$database   = $connection->getDatabase();
		$collection = $database.'.'.$this->_table;
		$tree       = $this->_parse($this->_condition);
		$where      = $this->_bind($tree, $this->_bind);
		$result     = [];

		if($this->_record) {
			$options = [];
			if($this->_columns) {
				$options['projection'] = $this->_columns;
			}
			$options['skip']  = $this->_offset;
			$options['limit'] = $this->_count;
			$options['sort']  = $this->_order;
			$query  = new MongoQuery($where, $options);
			$cursor = $manager->executeQuery($collection, $query)->toArray();

			foreach($cursor as $row) {
				$row = (array)$row;
				if(isset($row['_id'])) {
					$row['_id'] = strval($row['_id']);
				}
				$result[] = $this->_stdToArray($row);
			}

		} else {
			$ops = array(array('$match'=>$where));

			$this->_aggregate['_id'] = $this->_group ? $this->_group : null;
			$ops[] = array('$group'=>$this->_aggregate);

			if($this->_having) {
				$tree   = $this->_parse($this->_having);
				$having = $this->_bind($tree, $this->_bind);
				$ops[]  = array('$match'=>$having);
			}

			if(!empty($this->_order)) {
				$ops[] = array('$sort'=>$this->_order);
			}

			$ops[] = array('$skip' =>$this->_offset);
			$ops[] = array('$limit'=>$this->_count);

			$command = new Command(array(
				'aggregate' => $this->_table,
				'pipeline'  => $ops,
				'cursor'    => new \stdClass,
			));

			$cursor = $manager->executeCommand($database, $command)->toArray();
			if($this->_group) {
				foreach($cursor as $key=>$row) {
					$row = (array)$row;
					$row = array_merge((array)$row['_id'], $row);
					unset($row['_id']);
					$result[$key] = $this->_stdToArray($row);
				}
			} else {
				foreach($cursor as $key=>$row) {
					$row = (array)$row;
					unset($row['_id']);
					$result[$key] = $this->_stdToArray($row);
				}
			}
		}

		$this->_reset();

		if(!isset($result[0])) {
			return null;
		} elseif($resultset===false) {
			return $result;
		} elseif($this->_record===false) {
			return static::$_locator->make('resultset', [$result]);
		} else {
			return static::$_locator->make('resultset', [$result, $this]);
		}
	}

	public function execute():string
	{
		$connection = $this->_model->getMaster();
		$manager    = $connection->getManager();
		$database   = $connection->getDatabase();
		$collection = $database.'.'.$this->_table;
		$bulk       = new BulkWrite();

		if($this->_type!==static::INSERT) {
			$tree     = $this->_parse($this->_condition);
			$criteria = $this->_bind($tree, $this->_bind);
			$query    = new MongoQuery($criteria, array(
						'projection' => array('_id'=>1),
						'skip'       => 0,
						'limit'      => $this->_count,
			));
			$cursor   = $manager->executeQuery($collection, $query)->toArray();

			if(count($cursor)>0) {
				$keys = [];
				foreach($cursor as $row) {
					$keys[] = $row->_id;
				}
				$criteria = array('_id'=>array('$in'=>$keys));
			} else {
				$this->_reset();
				return '0';
			}
		}

		switch($this->_type) {
			case static::INSERT :
					$bulk->insert($this->_data);
					$manager->executeBulkWrite($collection, $bulk);
					$result = $this->_data['_id'];
					break;
			case static::UPDATE :
					$bulk->update($criteria, array('$set'=>$this->_data), array('multi'=>true));
					$result = $manager->executeBulkWrite($collection, $bulk)->getModifiedCount();
					break;
			case static::DELETE :
					$bulk->delete($criteria, array('limit'=>0));
					$result = $manager->executeBulkWrite($collection, $bulk)->getDeletedCount();
					break;
			default             :
					$result = '0';
		}

		$this->_reset();

		return strval($result);
	}

	protected function _parse($condition)
	{
		if($this->_registry) {
			$cache = $this->_registry->get($condition);
			if($cache) {
				return $cache;
			}
		}

		$tokens = mongodb\tokenizer::tokenize($condition);
		$tree   = mongodb\parser::parse($tokens);

		if($this->_registry) {
			$this->_registry->set($condition, $tree);
		}

		return $tree;
	}

	protected function _condition($condition, array $bind)
	{
		switch(gettype($condition))
		{
			case 'string' :
					if(!ctype_alnum($condition)) {
						break;
					}
			case 'integer':
					$bind      = array($condition);
					$condition = '_id=?';
					break;
			case 'array'  :
					$bind      = array($condition);
					$condition = '_id IN(?)';
					break;
			default      :
					throw new \LogicException('syntax error');
			break;
		}

		return array('condition'=>$condition, 'bind'=>$bind);
	}

	protected function _bind(array &$tree, array &$bind=null)
	{
		foreach($tree as $key=>$conds) {
			if($key==='_id') {
				$value = array_shift($bind);
				if(is_string($value) and strlen($value)===24) {
					$value = new ObjectID($value);
				} elseif(is_array($value)) {
					foreach($value as $index=>$id) {
						if(is_string($id) and strlen($id)===24) {
							$value[$index] = new ObjectID($id);
						}
					}
				}
				array_unshift($bind, $value);
			}

			if(is_array($conds)) {
				$tree[$key] = $this->{__FUNCTION__}($conds, $bind);
			} elseif($conds==='?') {
				$value = array_shift($bind);
				if($value===null) { throw new \InvalidArgumentException('SQL parameter is missing'); }
				if($key==='$like') {
					unset($tree[$key]);
					$head   = substr($value, 0, 1);
					$tail   = substr($value, -1);
					$middle = substr($value, 1, -1);
					$head   = $head==='%' ? '' : '^'.$head;
					$tail   = $tail==='%' ? '' : $tail.'$';
					$middle = str_replace('%', '.+', $middle);
					$middle = str_replace('_', '.', $middle);
					$value  = $head.$middle.$tail;
					$key    = '$regex';
				} elseif($key==='$near') {
					if(!is_array($value) and count($value)>1) {
						throw new \LogicException('syntax error');
					}

					$longitude = floatval(array_shift($value));
					$latitude  = floatval(array_shift($value));
					$distance  = count($value)===0 ? 2000 : intval(array_shift($value));
					$value     = array(
						'$geometry'   => array(
							'type'        => 'Point',
							'coordinates' => array($longitude, $latitude)
						),
						'$maxDistance'=> $distance,
					);
				}
				$tree[$key] = $value;
			} elseif($key==='$exists') {
				continue;
			} else {
				throw new \LogicException('syntax error');
			}
		}

		return $tree;
	}

	private function _stdToArray($result)
	{
		$array = [];
		foreach($result as $key=>$value) {
			if(is_object($value)) {
				switch(get_class($value)) {
					case 'MongoDB\BSON\ObjectID'   :
									$value = strval($value);
									break;
					case 'stdClass'                :
									$value = $this->{__FUNCTION__}($value);
									break;
					case 'MongoDB\BSON\Timestamp'  :
									$time  = strval($value);
									$value = intval(substr($time, strpos($time, ':')+1, -1));
									break;
					case 'MongoDB\BSON\UTCDateTime':
									$value = strval($value);
									break;
					case 'MongoDB\BSON\Regex'      :
									$value = strval($value);
									break;
					case 'MongoDB\BSON\Binary'     :
									$value = $value->getData();
									break;
				}
			}

			$array[$key] = $value;
		}

		return $array;
	}

	private function _reset()
	{
		$this->_columns  = null;
		$this->_condition= '_id is not null';
		$this->_bind     = [];
		$this->_aggregate= null;
		$this->_group    = '';
		$this->_having   = '';
		$this->_order    = [];
		$this->_offset   = 0;
		$this->_count    = 20;
		$this->_state    = 0;
		$this->_data     = [];
	}
}