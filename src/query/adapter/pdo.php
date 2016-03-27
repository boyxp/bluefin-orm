<?php
declare(strict_types=1);
namespace bluefin\orm\query\adapter;
use bluefin\orm\model\model;
use bluefin\orm\record\record;
use bluefin\orm\resultset\resultset;
use bluefin\orm\query\query as queryInterface;
class pdo extends \injector implements queryInterface
{
	private $_model     = null;
	private $_table     = null;
	private $_key       = null;

	private $_state     = 0;
	private $_type      = null;

	private $_columns   = '*';
	private $_condition = '1';
	private $_bind      = [];
	private $_group     = '';
	private $_having    = '';
	private $_order     = [];
	private $_offset    = 0;
	private $_count     = 20;

	private $_fields    = [];
	private $_values    = [];

	const INSERT = 'INSERT';
	const SELECT = 'SELECT';
	const UPDATE = 'UPDATE';
	const DELETE = 'DELETE';

	public function __construct(model $model)
	{
		$this->_model = $model;
		$this->_table = $model->getTable();
		$this->_key   = $model->getKey();
	}

	public function insert(array $data):queryInterface
	{
		if($this->_state >= 1) { throw new \LogicException('syntax error'); }

		$this->_type   = static::INSERT;
		$this->_fields = array_keys($data);
		$this->_values = array_values($data);
		$this->_state  = 7;

		return $this;
	}

	public function update(array $data):queryInterface
	{
		if($this->_state >= 1) { throw new \LogicException('syntax error'); }

		$this->_type = static::UPDATE;

		if(isset($data[$this->_key])) {
			$key = $data[$this->_key];
			unset($data[$this->_key]);

			$this->_fields = array_keys($data);
			$this->_values = array_values($data);

			return $this->where($key);
		} else {
			$this->_fields = array_keys($data);
			$this->_values = array_values($data);
			$this->_state = 2;

			return $this;
		}
	}

	public function delete(array $data=null):queryInterface
	{
		if($this->_state >= 1) { throw new \LogicException('syntax error'); }

		$this->_type = static::DELETE;

		if(isset($data[$this->_key])) {
			return $this->where(strval($data[$this->_key]));
		}

		$this->_state = 2;

		return $this;
	}

	public function select(string $columns='*'):queryInterface
	{
		if($this->_state >= 1) { throw new \LogicException('syntax error'); }

		if(strpos($columns, '(')!==false and preg_match('/(?:,|^|\s)(?:avg|count|max|min|sum)\s*\(/i', $columns)) {
			$this->_record  = false;
			$this->_columns = $columns;
		} else {
			$this->_record  = true;
			$this->_columns = $columns.','.$this->_key;
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

		$this->_group = "GROUP BY {$fields}";
		$this->_state = 4;

		return $this;
	}

	public function having(string $condition, array $bind=null):queryInterface
	{
		if($this->_state != 4) { throw new \LogicException('syntax error'); }

		$this->_bind   = is_null($bind) ? $this->_bind : array_merge($this->_bind, $bind);
		$this->_having = "HAVING {$condition}";
		$this->_state  = 5;

		return $this;
	}

	public function order(string $field, string $direction='ASC'):queryInterface
	{
		if($this->_state > 6) { throw new \LogicException('syntax error'); }

		$this->_order[] = $field.' '.$direction;
		$this->_state   = 6;

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
		$query     = $this->__toString();
		$connection= $this->_model->getSlave();
		$statement = $connection->prepare($query);
		$statement->execute($this->_bind);
		$result    = $statement->fetchAll(\PDO::FETCH_ASSOC);

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
		$query     = $this->__toString();
		$bind      = array_merge($this->_values, $this->_bind);
		$connection= $this->_model->getMaster();
		$statement = $connection->prepare($query);
		$statement->execute($bind);
		$statement->closeCursor();

		$this->_reset();

		if($this->_type===static::INSERT) {
			return $connection->lastInsertId();
		} else {
			return strval($statement->rowCount());
		}
	}

	public function __toString()
	{
		switch($this->_type) {
			case static::INSERT : $query =  "INSERT INTO {$this->_table}(".join($this->_fields, ',').")VALUES(?".str_repeat(',?', count($this->_fields)-1).")";
					      break;
			case static::SELECT : $query =  "SELECT {$this->_columns} FROM {$this->_table} "
							."WHERE {$this->_condition} {$this->_group} {$this->_having} "
							.(isset($this->_order[0]) ? " ORDER BY ".implode(',', $this->_order) : "")
							." LIMIT {$this->_offset},{$this->_count}";
					      break;
			case static::UPDATE : $query =  "UPDATE {$this->_table} SET ".join($this->_fields, '=?,')."=? "
							."WHERE {$this->_condition} "
							.(isset($this->_order[0]) ? " ORDER BY ".implode(',', $this->_order) : "")
							." LIMIT {$this->_count}";
					      break;
			case static::DELETE : $query =  "DELETE FROM {$this->_table} "
							."WHERE {$this->_condition} "
							.(isset($this->_order[0]) ? " ORDER BY ".implode(',', $this->_order) : "")
							." LIMIT {$this->_count}";
					      break;
		}

		return $query;
	}

	protected function _condition($condition, array $bind=null)
	{
		switch(gettype($condition))
		{
			case 'string' :
					if(!ctype_alnum($condition)) {
						if(strpos($condition, '(?)')!==false and is_array($bind)) {
							$condition= str_replace('(?)', '(%s)', $condition);
							$temp     = [];
							$holders  = [];
							foreach($bind as $param) {
								if(is_array($param)) {
									$holders[] = '?'.str_repeat(',?', count($param)-1);
									$temp      = array_merge($temp, $param);
								} else {
									$temp[] = $param;
								}
							}

							$bind      = $temp;
							$condition = vsprintf($condition, $holders);
						}
						break;
					}
			case 'integer':
					$bind      = array($condition);
					$condition = $this->_key.'=?';
					break;
			case 'array'  :
					$bind      = $condition;
					$condition = $this->_key.' IN(?'.str_repeat(',?', count($bind)-1).')';
					break;
			default      :
					throw new \LogicException('syntax error');
			break;
		}

		return array('condition'=>$condition, 'bind'=>$bind);
	}

	private function _reset()
	{
		$this->_columns  = '*';
		$this->_condition= '1';
		$this->_bind     = [];
		$this->_group    = '';
		$this->_having   = '';
		$this->_order    = [];
		$this->_offset   = 0;
		$this->_count    = 20;
		$this->_state    = 0;
		$this->_fields   = [];
		$this->_values   = [];
	}
}
