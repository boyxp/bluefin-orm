<?php
declare(strict_types=1);
namespace bluefin\orm\model\adapter;
use bluefin\orm\query\query;
use bluefin\orm\record\record;
use bluefin\orm\connection\connection;
use bluefin\orm\model\model as modelInterface;
class model extends \injector implements modelInterface
{
	const DRIVER = 'pdo';

	private static $_query = [];

	public static function insert(array $data=null):record
	{
	}

	public static function select(string $columns='*'):query
	{
	}

	public static function update(array $data):query
	{
	}

	public static function delete(array $data=null):query
	{
	}

	public static function getDriver():string
	{
		return static::DRIVER;
	}

	public static function getMaster():connection
	{
	}

	public static function getSlave():connection
	{
	}

	public static function getDatabase():string
	{
	}

	public static function getTable():string
	{
	}

	public static function getKey():string
	{
	}

	protected static function _getQueryInstance()
	{
		if(!isset(static::$_query[static::class])) {
			$driver = static::getDriver();
			static::$_query[static::class] = static::$_locator->make("query_{$driver}", array(new static));
		}

		return static::$_query[static::class];
	}
}
