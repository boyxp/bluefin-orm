<?php
namespace bluefin\orm\query;
interface query
{
	public function insert(array $data):query;
	public function update(array $data):query;
	public function delete(array $data=null):query;
	public function select(string $columns='*'):query;
	public function from():query;
	public function where($condition, array $bind=null):query;
	public function group(string $fields):query;
	public function having(string $condition, array $bind=null):query;
	public function order(string $field, string $direction='ASC'):query;
	public function limit(int $rows, int $offset=0):query;
	public function fetch(bool $record=false);
	public function fetchAll(bool $resultset=false);
	public function execute():string;
}
