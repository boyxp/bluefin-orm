<?php
declare(strict_types=1);
namespace bluefin\orm\record\adapter;
use Countable;
use Iterator;
use ArrayAccess;
use bluefin\orm\query\query;
use bluefin\orm\record\record as recordInterface;
class record implements recordInterface
{
	private $_data    = null;
	private $_query   = null;
	private $_key     = null;
	private $_created = false;

	public function __construct(array $data=null, query $query=null)
	{
		$this->_query   = $query;
		$this->_created = $data===null;
		$this->_data    = $data===null ? [] : $data;
	}

	public function __get(string $column)
	{
		return isset($this->_data[$column]) ? $this->_data[$column] : null;
	}

	public function __set(string $column, $value)
	{
		$this->_data[$column] = $value;
	}

	public function save():string
	{
		if(is_null($this->_query)) {
			return '';
		}

		if($this->_created) {
			$this->_key     = $this->_query->insert($this->_data)->execute();
			$this->_created = false;
		} elseif($this->_key) {
			$this->_query->update($this->_data)->where($this->_key)->execute();
		} else {
			$this->_query->update($this->_data)->execute();
		}

		return strval($this->_key);
	}

	public function delete():bool
	{
		if(is_null($this->_query)) {
			return false;

		} elseif($this->_created===false) {
			$this->_query->delete($this->_data)->execute();
			$this->_key     = null;
			$this->_data    = [];
			$this->_created = true;

			return true;
		} else {
			return false;
		}
	}

	public function execute():string
	{
		return $this->_query->insert($this->_data)->execute();
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
		return current($this->_data);
	}

	public function key()
	{
		return key($this->_data);
	}

	public function next()
	{
		next($this->_data);
	}

	public function rewind()
	{
		reset($this->_data);
	}

	public function valid()
	{
		return current($this->_data)!==false;
	}

	//ArrayAccess
	public function offsetSet($key, $value)
	{
		if(!is_null($key)) {
			$this->_data[$key] = $value;
		}
	}

	public function offsetExists($key)
	{
		return isset($this->_data[$key]);
	}

	public function offsetUnset($key)
	{
		unset($this->_data[$key]);
	}

	public function offsetGet($key)
	{
		return isset($this->_data[$key]) ? $this->_data[$key] : null;
	}
}
