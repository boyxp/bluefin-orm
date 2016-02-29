<?php
namespace connection;
interface connection
{
	public function begin():bool;
	public function commit():bool;
	public function rollback():bool;
}
