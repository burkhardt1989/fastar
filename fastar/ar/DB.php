<?php

namespace FastAR;

class DB
{
	private static $instance = array();
	private $link;
	private $stm;
	private $config;

	protected function __construct($config) {
		$this->config = $config;
	}

	public static function getInstance($config = array()) {
		if (!isset(self::$instance[$config['flag']])) {
			self::$instance[$config['flag']] = new self($config);
		}
		self::$instance[$config['flag']]->setConfig($config);
		return self::$instance[$config['flag']];
	}

	private function  __clone()
	{
	}

	private function connect()
	{
		$dsn = 'mysql:';
		if(isset($this->config['host']))
		$dsn .= isset($this->config['host'])?'host='.$this->config['host']:'host=localhost';
		$dsn .= isset($this->config['port'])?';port='.$this->config['port']:'';
		$dsn .= isset($this->config['dbname'])?';dbname='.$this->config['dbname']:'';
		$this->link = new \PDO($dsn, $this->config['user'], $this->config['pass'], $this->config['options']);
		$this->exec('set names '.$this->config['charset']);
		//self::$instance[$this->config['flag']] = $this;
	}

	public function close()
	{
		$this->link = null;
		$this->stm = null;
		//unset(self::$instance[$this->config['flag']]);
	}

	public function reconnect()
	{
		$this->close();
		$this->connect();
	}

	public static function closeAll()
	{
		foreach(self::$instance as $instance){
			$instance->close();
		}
	}

	public function find($confition = array(), $param = array())
	{
		// App::Log()->timeStart('mysql');
		$param['limit'] = 1;
		$sql = $this->buildQuery($confition, $param);
		// var_dump($sql);exit;
		// Log::sqllog($sql, 'find sql');
		// Log::sqllog($confition, 'find confition');
		// Log::sqllog($param, 'find param');
		// Log::sqllog(microtime(true),"sql find start");
		$this->prepare($sql);
		$this->bindValues($confition, 'w');
		$res = $this->execute('fetch');
		$this->stm->closeCursor();
		// Log::sqllog(microtime(true),"sql find end");
		// App::Log()->timeEnd('mysql');
		return $res;
	}

	public function findAll($confition = array(), $param = array())
	{
		App::Log()->timeStart('mysql');
		$sql = $this->buildQuery($confition, $param);
		Log::sqllog($sql, 'findAll sql');
		Log::sqllog($confition, 'findAll confition');
		Log::sqllog($param, 'findAll param');
		Log::sqllog(microtime(true),"sql findAll start");
		$this->prepare($sql);
		$this->bindValues($confition, 'w');
		$res = $this->execute('fetchAll');
		$this->stm->closeCursor();
		Log::sqllog(microtime(true),"sql findAll end");
		App::Log()->timeEnd('mysql');
		return $res;
	}

	public function insert($table, $data)
	{
		App::Log()->timeStart('mysql');
		$sql = $this->buildInsert($table, $data);
		Log::sqllog($sql, 'insert sql');
		Log::sqllog($data, 'insert data');
		Log::sqllog(microtime(true),"sql insert start");
		$this->prepare($sql);
		$this->bindValues($data);
		$this->execute();
		$res = $this->stm->rowCount();
		//$res = $this->link->lastInsertId();
		$this->stm->closeCursor();
		Log::sqllog(microtime(true),"sql insert end");
		App::Log()->timeEnd('mysql');
		return $res;
	}

	public function replace($table, $data)
	{
		App::Log()->timeStart('mysql');
		$sql = $this->buildInsert($table, $data, 'REPLACE');
		Log::sqllog($sql, 'replace sql');
		Log::sqllog($data, 'replace data');
		Log::sqllog(microtime(true),"sql replace start");
		$this->prepare($sql);
		$this->bindValues($data);
		$this->execute();
		$res = $this->stm->rowCount();
		//$res = $this->link->lastInsertId();
		$this->stm->closeCursor();
		Log::sqllog(microtime(true),"sql replace end");
		App::Log()->timeEnd('mysql');
		return $res;
	}

	public function update($table, $data, $confition)
	{
		App::Log()->timeStart('mysql');
		$sql = $this->buildUpdate($table, $data, $confition);
		Log::sqllog($sql, 'update sql');
		Log::sqllog($data, 'update data');
		Log::sqllog($confition, 'update confition');
		Log::sqllog(microtime(true),"sql update start");
		$this->prepare($sql);
		$this->bindValues($data);
		$this->bindValues($confition, 'w');
		$this->execute();
		$res = $this->stm->rowCount();
		$this->stm->closeCursor();
		Log::sqllog(microtime(true),"sql update end");
		App::Log()->timeEnd('mysql');
		return $res;
	}

	public function delete($table, $confition)
	{
		App::Log()->timeStart('mysql');
		$sql = $this->buildDelete($table, $confition);
		Log::sqllog($sql, 'delete sql');
		Log::sqllog($confition, 'delete confition');
		Log::sqllog(microtime(true),"sql delete start");
		$this->prepare($sql);
		$this->bindValues($confition, 'w');
		$this->execute();
		$res = $this->stm->rowCount();
		$this->stm->closeCursor();
		Log::sqllog(microtime(true),"sql delete end");
		App::Log()->timeEnd('mysql');
		return $res;
	}

	private function buildQuery($confition, $param = array())
	{
		$default = array(
			'distinct' => '',
			'select' => '*',
			'from' => 'test',
			'where' => '',
			'group' => '',
			'having' => '',
			'order' => '',
			'limit' => -1,
			'offset' => -1,
		);
		$default['where'] = $this->buildWhere($confition);
		if(!$default['where'])
			$default['where'] = '1';
		$query = array_merge($default, $param);


		$sql = !empty($query['distinct']) ? 'SELECT DISTINCT' : 'SELECT';
		$sql .= ' '.(!empty($query['select']) ? $query['select'] : '*').' ';

		$sql.="\nFROM `".$query['from']."` ";
		if(!empty($query['where']))
			$sql.="\nWHERE ".$query['where'];

		if(!empty($query['group']))
			$sql.="\nGROUP BY ".$query['group'];

		if(!empty($query['having']))
			$sql.="\nHAVING ".$query['having'];

		if(!empty($query['order']))
			$sql.="\nORDER BY ".$query['order'];

		$limit = isset($query['limit']) ? (int)$query['limit'] : -1;
		$offset = isset($query['offset']) ? (int)$query['offset'] : -1;
		if($limit >= 0)
			$sql .= ' LIMIT '.(int)$limit;
		if($offset > 0)
			$sql .= ' OFFSET '.(int)$offset;
		return $sql;
	}

	private function buildInsert($table, $data, $act = "INSERT")
	{
		$value = array();
		$field = array();
		$isarray = false;
		foreach($data as $key => $val)
		{
			if(is_array($val))
			{
				$field = empty($field)?array_keys($val):$field;
				$vals = array();
				foreach($val as $k => $v)
				{
					$vals[] = ":{$k}{$key}";
				}
				$value[] = '('.implode(',', $vals).')';
				$isarray = true;
			} else {
				$value[] = ":{$key}";
			}
		}
		$values = $isarray?implode(",", $value):"(".implode(",", $value).")";
		$field = empty($field)?array_keys($data):$field;
		$sql = $act." INTO `".$table."` ";
		$sql .= "(`".implode('`,`', $field)."`) ";
		$sql .= "VALUES ";
		$sql .= $values;
		$sql .= ";";
		//echo $sql;
		return $sql;
	}

	private function buildUpdate($table, $data, $confition)
	{
		$sql = "UPDATE `".$table."` ";
		$set = array();
		foreach($data as $field => $value)
		{
			$set[] = "`{$field}`=:{$field}";
		}
		$sql .= "SET ".implode(',', $set)." ";
		$where = $this->buildWhere($confition);
		$sql .= "WHERE ".$where;
		return $sql;
	}

	private function buildDelete($table, $confition)
	{
		$sql = "DELETE FROM `".$table."` ";
		$where = $this->buildWhere($confition);
		$sql .= "WHERE ".$where;
		return $sql;
	}

	private function buildWhere($confition)
	{
		$where = array();
		foreach($confition as $field => $value){
			$not = strpos($field, '!') === 0?true:false;
			if(is_int($field)){
				$where[] = $value;
			} elseif(is_array($value)){
				$field = ltrim($field, '!');
				$in = array();
				foreach($value as $k => $v){
					$in[] = ":w{$field}{$k}";
				}
				if(!empty($in)){
					$instr = $not?'not in':'in';
					$where[] = "`{$field}` ".$instr." (".implode(',', $in).")";
				}
			} else {
				$field = ltrim($field, '!');
				$instr = $not?'!=':'=';
				$where[] = "`{$field}` ".$instr." :w{$field}";
			}
		}
		if(!empty($where)){
			return implode(' AND ', $where);
		} else {
			return '1';
		}
	}

	private function bindValues($condition, $type = '')
	{
		foreach($condition as $name => $value)
		{
			if(is_int($name) && !is_array($value)){
				continue;
			} elseif(is_array($value)) {
				foreach($value as $n => $v)
				{
					if(is_int($name)){
						$this->stm->bindValue($type.$n.$name, $v);
					} else {
						$name = ltrim($name, '!');
						$this->stm->bindValue($type.$name.$n, $v);
					}
				}
			} else {
				$name = ltrim($name, '!');
				$this->stm->bindValue($type.$name, $value);
			}
		}
	}

	private function prepare($sql)
	{
		if($this->link == null)
			$this->connect();
		$this->stm = $this->link->prepare($sql);
	}

	private function execute($method = '', $fetch_style = \PDO::FETCH_ASSOC)
	{
		$res = $this->stm->execute();
		if(!$res)
		{
			$errmsg = $this->stm->errorInfo();
			throw new Exception("MYSQL Error: {$errmsg['2']}", 500);
			//echo 'MYSQL Error: '.$errmsg['2'].PHP_EOL;
		}
		if(in_array($method, array('fetch', 'fetchAll'))){
			$res = $this->stm->$method($fetch_style);
		}
		//$this->stm->closeCursor();
		return $res;
	}

	public function lastInsertId()
	{
		return $this->link->lastInsertId();
	}

	public function exec($sql)
	{
		if($this->link == null)
			$this->connect();
		return $this->link->exec($sql);
	}

	public function query($sql)
	{
		if($this->link == null)
			$this->connect();
		return $this->link->query($sql);
	}

	public function setConfig($config)
	{
		$this->config += $config;
	}
}