<?php
declare(strict_types=1);
namespace bluefin\orm\resultset\adapter;
use Countable;
use Iterator;
use ArrayAccess;
use bluefin\orm\query\query;
use bluefin\orm\resultset\resultset as resultsetInterface;
class resultset extends \injector implements resultsetInterface
{
	private $_query   = null;
	private $_data    = null;
	private $_positon = 0;
	private $_record  = [];

	public function __construct(array $data, query $query=null)
	{
		$this->_data  = $data;
		$this->_query = $query;
	}

	public function offset(int $row=0, int $offset=0)
	{
		if(isset($this->_data[$row]) and count($this->_data[$row])>$offset) {
			$values = array_values($this->_data[$row]);
			return $values[$offset];
		}

		return null;
	}

	public function column(string $column=null, string $index=null):array
	{
		return array_column($this->_data, $column, $index);
	}

	public function each(callable $callback):resultsetInterface
	{
		array_map($callback, $this->_data);
		return $this;
	}

	public function map(callable $callback):resultsetInterface
	{
		$this->_data = array_map($callback, $this->_data);
		return $this;
	}

	public function join(resultsetInterface $result, string $leftKey=null, string $rightKey=null):resultsetInterface
	{
		if(is_null($leftKey) and is_null($rightKey)) {
			$key = array_intersect(array_keys($this->_data[0]), array_keys($result->toArray()[0]));
			if(!isset($key[0])) { return $this; }
			$leftKey = $rightKey = $key[0];

		} elseif(is_null($rightKey)) {
			$rightKey = $leftKey;
		}

		$right = $result->column(null, $rightKey);
		$temp  = [];
		foreach($this->_data as $leftRow) {
			$leftItem = $leftRow[$leftKey];
			$rightRow = isset($right[$leftItem]) ? $right[$leftItem] : [];
			$temp[]   = array_merge($leftRow, $rightRow);
		}

		$this->_data = $temp;

		return $this;
	}

	public function toArray():array
	{
		return $this->_data;
	}

	//Countable
	public function count()
	{
		return count($this->_data);
	}


	//Iterator
	public function current()
	{
		if(!isset($this->_record[$this->_positon])) {
			$this->_record[$this->_positon] = static::$_locator->make('record', array($this->_data[$this->_positon], $this->_query));
		}

		return $this->_record[$this->_positon];
	}

	public function key()
	{
		return $this->_positon;
	}

	public function next()
	{
		++$this->_positon;
	}

	public function rewind()
	{
		$this->_positon = 0;
	}

	public function valid()
	{
		return isset($this->_data[$this->_positon]);
	}


	//ArrayAccess
	public function offsetSet($offset, $value)
	{
		if(is_null($offset)) {
			$this->_data[] = $value;
		} else {
			$this->_data[$offset] = $value;
		}
	}

	public function offsetExists($offset)
	{
		return isset($this->_data[$offset]);
	}

	public function offsetUnset($offset)
	{
		unset($this->_data[$offset]);
	}

	public function offsetGet($offset)
	{
		if(!isset($this->_data[$offset])) {
			return null;
		}

		if(!isset($this->_record[$offset])) {
			$this->_record[$offset] = static::$_locator->make('record', array($this->_data[$offset], $this->_query));
		}

		return $this->_record[$offset];
	}
}
