<?php
declare(strict_types=1);
namespace bluefin\orm\model\adapter;
use bluefin\orm\query\query;
use bluefin\orm\record\record;
use bluefin\orm\connection\connection;
use bluefin\orm\model\model as modelInterface;
class model extends \injector implements modelInterface
{
	const DRIVER   = 'pdo';
	const MASTER   = '';
	const SLAVE    = '';
	const DATABASE = '';
	const TABLE    = '';
	const KEY      = '';

	protected static $_query = [];

	public static function insert(array $data=null):record
	{
		$query = static::_getQueryInstance();
		return static::$locator->make('record', array($data, $query));
	}

	public static function select(string $columns='*'):query
	{
		return static::_getQueryInstance()->select($columns);
	}

	public static function update(array $data):query
	{
		return static::_getQueryInstance()->update($data);
	}

	public static function delete(array $data=null):query
	{
		return static::_getQueryInstance()->delete($data);
	}

	public static function getDriver():string
	{
		return static::DRIVER;
	}

	public static function getMaster():connection
	{
		return static::$locator->get(static::MASTER);
	}

	public static function getSlave():connection
	{
		return static::$locator->get(static::SLAVE);
	}

	public static function getDatabase():string
	{
		return static::DATABASE;
	}

	public static function getTable():string
	{
		return static::TABLE;
	}

	public static function getKey():string
	{
		return static::KEY;
	}

	protected static function _getQueryInstance()
	{
		if(!isset(static::$_query[static::class])) {
			$driver = static::getDriver();
			static::$_query[static::class] = static::$locator->make("query_{$driver}", array(new static));
		}

		return static::$_query[static::class];
	}
}
