<?php
namespace record;
use Countable;
use Iterator;
use ArrayAccess;
interface record extends Countable,Iterator,ArrayAccess
{
	public function __get(string $column);
	public function __set(string $column, $value);
	public function save():string;
	public function delete():bool;
}
