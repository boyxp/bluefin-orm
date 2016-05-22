<?php
declare(strict_types=1);
namespace bluefin\orm\connection\adapter;
use bluefin\orm\connection\connection as connectionInterface;
class mongodb implements connectionInterface
{
	protected $_manager  = null;
	protected $_database = null;

	public function __construct($dsn, array $options=null)
	{
		$options = $options===null ? array('w'=>1) : $options;

		if(preg_match('/\/([a-z0-9_]+)(?:\?|$)/i', $dsn, $matches)) {
			$this->_database = $matches[1];
		} else {
			$this->_database = 'development';
		}

		if(!isset($options['maxPoolSize'])) {
			$options['maxPoolSize'] = 10;
		}

		try {
			$manager = new \MongoDB\Driver\Manager($dsn, $options);
			$command = new \MongoDB\Driver\Command(['ping' => 1]);
			$manager->executeCommand('db', $command);

		} catch (\exception $e) {
			throw new \InvalidArgumentException('Connection failed: '.$e->getMessage(), $e->getCode());
		}

		$this->_manager = $manager;
	}

	public function getManager()
	{
		return $this->_manager;
	}

	public function getDatabase()
	{
		return $this->_database;
	}

	public function begin():bool
	{
		return true;
	}

	public function commit():bool
	{
		return true;
	}

	public function rollback():bool
	{
		return true;
	}
}
