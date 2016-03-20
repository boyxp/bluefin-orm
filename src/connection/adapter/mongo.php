<?php
declare(strict_types=1);
namespace bluefin\orm\connection\adapter;
use bluefin\orm\connection\connection as connectionInterface;
class mongo extends \MongoDB implements connectionInterface
{
	protected static $_connection = array();

	public function __construct($dsn, array $options=null)
	{
		$options = $options===null ? array('connect'=>true) : $options;
		if($user) {
			$options['username'] = $user;
		}

		if($password) {
			$options['password'] = $password;
		}

		$options['connectTimeoutMS'] = isset($options['connectTimeoutMS']) ? $options['connectTimeoutMS'] : 2000;
		$options['db']               = isset($options['db']) ? $options['db'] : 'test';

		try {
			if(!isset(static::$_connection[$dsn])) {
				static::$_connection[$dsn] = new \MongoClient($dsn, $options);
			}

			parent::__construct(static::$_connection[$dsn], $options['db']);
		} catch (exception $e) {
			throw new \InvalidArgumentException('Connection failed: '.$e->getMessage(), $e->getCode());
		}
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
