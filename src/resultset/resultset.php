<?php
namespace bluefin\orm\resultset;
use Countable;
use Iterator;
use ArrayAccess;
interface resultset extends Countable,Iterator,ArrayAccess
{
	public function offset(int $row=0, int $offset=0);
	public function column(string $column=null, string $index=null):array;
	public function each(callable $callback):resultset;
	public function map(callable $callback):resultset;
	public function join(resultset $result, string $leftKey=null, string $rightKey=null):resultset;
	public function toArray():array;
}
