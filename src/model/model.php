<?php
namespace bluefin\orm\model;
use bluefin\orm\query\query;
use bluefin\orm\record\record;
use bluefin\orm\connection\connection;
interface model
{
	public static function insert(array $data=null):record;
	public static function select(string $columns='*'):query;
	public static function update(array $data):query;
	public static function delete(array $data=null):query;
	public static function getConnection():connection;
}
