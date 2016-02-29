<?php
namespace resultset;
use Countable;
use Iterator;
use ArrayAccess;
interface resultset extends Countable,Iterator,ArrayAccess
{
	public function offset(int $row=0, int $offset=0);
	public function column(string $column=null, string $index=null):array;
	public function each(callable $callback):array;
	public function map(callable $callback):array;
	public function join(resultset $result, string $leftKey=null, string $rightKey=null):array;
}
