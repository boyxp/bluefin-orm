<?php
declare(strict_types=1);
namespace bluefin\orm\connection\adapter;
use bluefin\orm\connection\connection as connectionInterface;
class pdo extends \PDO implements connectionInterface
{
	public function __construct(string $dsn, string $user='', string $password='', array $options=null)
	{
		if(strpos(strtolower($dsn), 'charset=')!==false) {
			preg_match('/charset=([a-z0-9-]+)/i', $dsn, $match);
			$charset = isset($match[1]) ? $match[1] : 'utf8';
		} else {
			$charset = isset($options['charset']) ? $options['charset'] : 'utf8';
			$dsn    .= (substr($dsn, -1)===';' ? '' : ';')."charset={$charset}";
		}

		try {
			parent::__construct($dsn, $user, $password, array(\PDO::ATTR_PERSISTENT => false));
		} catch (exception $e) {
			throw new \InvalidArgumentException('Connection failed: '.$e->getMessage(), $e->getCode());
		}

		$this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);

		$timezone = isset($options['timezone']) ? $options['timezone'] : '+00:00';
		$this->exec("SET time_zone='{$timezone}'");
		$this->exec("SET NAMES '{$charset}'");
	}

	public function begin():bool
	{
		return parent::beginTransaction();
	}

	public function commit():bool
	{
		return parent::commit();
	}

	public function rollback():bool
	{
		return parent::rollback();
	}
}
