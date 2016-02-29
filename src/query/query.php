<?php
namespace query;
use record\record;
use resultset\resultset;
interface query
{
	public function insert(array $data):query;
	public function update(array $data):query;
	public function delete(array $data=null):query;
	public function select(string $columns='*'):query;
	public function from():query;
	public function where(string $condition, array $bind=null):query;
	public function group(string $fields):query;
	public function having(string $condition, array $bind=null):query;
	public function order(string $field, string $direction='ASC'):query;
	public function limit(int $offset, int $rows):query;
	public function fetch():record;
	public function fetchAll():resultset;
	public function execute():string;
}
