<?php
namespace pool;
use connection\connection;
interface pool
{
	public function __get(string $db):connection;
	public function __set(string $db, connection $connection):bool;
	public function __unset(string $db):bool;
	public function __isset(string $db):bool;
}
