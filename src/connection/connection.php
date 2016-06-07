<?php
declare(strict_types=1);
namespace bluefin\orm\connection;
interface connection
{
	public function begin():bool;
	public function commit():bool;
	public function rollback():bool;
}
