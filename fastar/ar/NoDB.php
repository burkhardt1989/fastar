<?php

namespace FastAR;

// require_once FASTAR_APP_DIR.'/lib/Predis/Autoloader.php';

class NoDB
{
	private static $instance = array();
	private $redis;
	private $config;
	private $prev = '';
	private $timeOut = 0;

	protected function __construct($config) {
		$this->config = $config;
		$this->prev = $config['prev'];
		$this->timeOut = $config['timeOut'];
	}

	public static function getInstance($config = array()) {
		if (!isset(self::$instance[$config['flag']])) {
			self::$instance[$config['flag']] = new self($config);
			//self::$instance[$config['flag']]->connect();//new好之后不连接
		}
		return self::$instance[$config['flag']];
	}

	private function  __clone()
	{
	}

	private function connect()
	{
		try {
			if(class_exists('Redis')){
				$this->redis = new Redis();
				$this->redis->connect($this->config['host'], $this->config['port']);
			} else {
				require_once '../fastar/lib/Predis/Autoloader.php';
				\Predis\Autoloader::register();
				$this->redis = new \Predis\Client($this->config);
			}
		} catch(PDOException $e) {
			echo $e->getMessage();
			die();
		}
		if(isset($this->config['pass']))
			$this->redis->auth($this->config['pass']);
		$this->redis->select($this->config['flag']);
	}

	public function close()
	{
		if($this->redis){
			$this->redis->close();
			$this->redis = null;
		}
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

	public function exec($method, $arguments = array()){
		if(!$this->redis)
			$this->connect();
		// App::Log()->timeStart('redis');
		$res = call_user_func_array(array($this->redis, $method), $arguments);
		// App::Log()->timeEnd('redis');
		// REDIS_LOG and Log::info(microtime(true), 'redis call '.$method.' '.json_encode($arguments));//.' '.json_encode($res));
		return $res;
	}

	public function set($key, $value, $timeOut = 0) {
		if(!is_numeric($value))
			$value = json_encode($value);
		else
			$value = number_format($value, 0, '', '');
		$retRes = $this->exec('set', array($this->prev.':'.$key, $value));
		$this->timeOut($key, $timeOut);
		return $retRes;
	}

	public function get($key) {
		$result = $this->exec('get', array($this->prev.':'.$key));
		return myJsonDecode($result);
	}

	public function del($key) {
		return $this->exec('del', array($this->prev.':'.$key));
	}

	public function hSet($key, $id, $value, $timeOut = 0) {
		$value = json_encode($value);
		$retRes = $this->exec('hSet', array($this->prev.':'.$key, $id, $value));
		$this->timeOut($key, $timeOut);
		return $retRes;
	}

	public function hGet($key, $id) {
		$result = $this->exec('hGet', array($this->prev.':'.$key, $id));
		return myJsonDecode($result);
	}

	public function hExists($key,$id){
		$result = $this->exec('hexists', array($this->prev.':'.$key, $id));
		return $result;
	}

	public function hDel($key, $id) {
		return $this->exec('hDel', array($this->prev.':'.$key, $id));
	}

	public function hMset($key, $value, $timeOut = 0) {
		if(empty($value))
			return;
		$value = array_map('json_encode', $value);
		$retRes = $this->exec('hMset', array($this->prev.':'.$key, $value));
		$this->timeOut($key, $timeOut);
		return $retRes;
	}

	public function hMget($key, $ids) {
		if($result = $this->exec('hMget', array($this->prev.':'.$key, $ids))){
			ksort($result);
			$count = count($result);
			return array_map('myJsonDecode', $result);
		} else {
			return $result;
		}
	}

	public function hGetAll($key) {
		if($result = $this->exec('hGetAll', array($this->prev.':'.$key))) {
			ksort($result);
			$count = count($result);
			return array_map('myJsonDecode', $result);
		} else {
			return $result;
		}
	}

	public function push($key, $value, $timeOut = 0, $right = true)
	{
		$method = $right ? 'rPush' : 'lPush';
		$retRes = $this->exec($method, array($this->prev . ':' . $key, json_encode($value)));
		$this->timeOut($key, $timeOut);
		return $retRes;
	}

	public function lRange($key, $start, $end) {
		return $this->exec('lRange', array($this->prev.':'.$key, $start, $end));
	}

	public function pop($key, $right = false) {
		$method = $right?'rPop':'lPop';
		return myJsonDecode($this->exec($method, array($this->prev.':'.$key)));
	}

	public function lIndex($key, $index = 0) {
		return myJsonDecode($this->exec('lIndex', array($this->prev.':'.$key, $index)));
	}

	public function lLen($key) {
		return $this->exec('lLen', array($this->prev.':'.$key));
	}

	public function incr($key, $timeOut = 0) {
		$id = $this->exec('incr', array($this->prev.':'.$key));
		$this->timeOut($key, $timeOut);
		return $id;
	}

	public function incrBy($key, $value, $timeOut = 0) {
		$id = $this->exec('incrby', array($this->prev.':'.$key, $value));
		$this->timeOut($key, $timeOut);
		return $id;
	}

	public function hIncrby($key, $field, $value, $timeOut = 0) {
		$id = $this->exec('hIncrby', array($this->prev.':'.$key, $field, $value));
		$this->timeOut($key, $timeOut);
		return $id;
	}

	public function timeOut($key, $timeOut) {
		$timeOut = $timeOut?$timeOut:$this->timeOut;
		if($timeOut > 0) $this->exec('expire', array($this->prev.':'.$key, $timeOut));
	}

	public function exists($key) {
		return $this->exec('exists', array($this->prev.':'.$key));
	}

	public function flushDB() {
		return $this->exec('flushDB');
	}

	public function setNx($key, $value) {
		return $this->exec('setnx', array($this->prev.':'.$key, $value));
	}

	/**
	 * [将一个或多个 member 元素及其 score 值加入到有序集 key 当中]
	 * @param  [string] $key     [有序集的key]
	 * @param  [array] $maps    [score member [[score member] [score member] ...]]
	 * @param  [int] $timeOut [过期时间]
	 * @return [type]          [被成功添加的新成员的数量，不包括那些被更新的、已经存在的成员]
	 */
	public function zAdd($key, $maps, $timeOut = 0)
	{
		array_unshift($maps, $this->prev.':'.$key);
		$return = $this->exec('ZADD', $maps);
		$this->timeOut($key, $timeOut);
		return $return;
	}

	/**
	 * [为有序集 key 的成员 member 的 score 值加上增量 increment]
	 * @param  [string] $key     [有序集的key]
	 * @param  [int] $value     [member增加的值]
	 * @param  [string] $id      [有序集member]
	 * @param  [int] $timeOut [过期时间]
	 * @return [int]          [member 成员的新 score 值，以字符串形式表示]
	 */
	public function zIncrby($key, $value, $id, $timeOut = 0)
	{
		$return = $this->exec('ZINCRBY', array($this->prev.':'.$key, $value, $id));
		$this->timeOut($key, $timeOut);
		return $return;
	}

	/**
	 * [返回有序集 key 中，指定区间内的成员(由小到大，0：第一个，-1：倒数第一个)]
	 * @param  [string]  $key        [有序集的key]
	 * @param  [int]  $start      [开始下标]
	 * @param  [int]  $stop       [结束下标]
	 * @param  boolean $withscores [是否返回score]
	 * @return [array]              [指定区间内，带有 score 值(可选)的有序集成员的列表。]
	 */
	public function zRange($key, $start, $stop, $withscores = false)
	{
		if ($withscores) {
			$result = $this->exec('ZRANGE', array($this->prev.':'.$key, $start, $stop, 'WITHSCORES'));
		} else {
			$result = $this->exec('ZRANGE', array($this->prev.':'.$key, $start, $stop));
		}
		return $result;
	}

	/**
	 * [返回有序集 key 中，指定区间内的成员(由大到小，0：第一个，-1：倒数第一个)]
	 * @param  [string]  $key        [有序集的key]
	 * @param  [int]  $start      [开始下标]
	 * @param  [int]  $stop       [结束下标]
	 * @param  boolean $withscores [是否返回score]
	 * @return [array]              [指定区间内，带有 score 值(可选)的有序集成员的列表。]
	 */
	public function zRevRange($key, $start, $stop, $withscores = false)
	{
		if ($withscores) {
			$result = $this->exec('ZREVRANGE', array($this->prev.':'.$key, $start, $stop, 'WITHSCORES'));
		} else {
			$result = $this->exec('ZREVRANGE', array($this->prev.':'.$key, $start, $stop));
		}
		return $result;
	}

	/**
	 * [移除有序集 key 中，指定排名(rank)区间内的所有成员。]
	 * @param  [string]  $key        [有序集的key]
	 * @param  [int]  $start      [开始下标]
	 * @param  [int]  $stop       [结束下标]
	 * @return [int]        [被移除成员的数量]
	 */
	public function zRemRangeByRank($key, $start, $stop)
	{
		$num = $this->exec('ZREMRANGEBYRANK', array($this->prev.':'.$key, $start, $stop));
		return $num;
	}

	/**
	 * [被成功移除的成员的数量，不包括被忽略的成员]
	 * @param  [string]  $key        [有序集的key]
	 * @param [array] $ids [member [member ...]]
	 * @return [int]        [被移除成员的数量]
	 */
	public function ZREM($key, $ids)
	{
		array_unshift($ids, $this->prev.':'.$key);
		$num = $this->exec('ZREM', $ids);
		return $num;
	}
}

function myJsonDecode($str){
	return json_decode($str, true);
}
