<?php

namespace FastAR;

include_once 'Model.php';

abstract class RModel extends Model
{
	protected $redis;
	protected $pkcache = false;
	protected $skcache = false;

	public function __construct($isNew = true)
	{
		$redisConfig = Config::getConfig('redis.table');
		$this->redis = NoDB::getInstance($redisConfig);
		parent::__construct($isNew);
	}

	public function findByPk($pk, $param = array())
	{
		$key = $this->getKeyByPk();
		$pkKey = $this->getPkKey($pk);

		$data = $this->pkcache?$this->redis->hGet($key, $pkKey):null;
		if($data === null){
			$obj = parent::findByPk($pk, $param);
			if($this->pkcache){
				$this->redis->hSet($key, $pkKey, $obj?$obj->Attributes:[]);
			}
		} elseif(!$data) {
			$obj = null;
		} else {
			$class = get_class($this);
			$obj = new $class(false);
			foreach($param as $attr => $value)
			{
				if($data[$attr] != $value){
					return null;
				}
			}
			$obj->_oldAttributes = $obj->_attributes = $data;
			$obj->init();
		}
		return $obj;
	}

	public function findBySk($sk,$param = array())
	{
		if(!$this->sk) return array();

		if(is_array($this->sk) && is_array($sk)){
			$key = '';
			foreach($sk as $k => $v){
				if(in_array($k, $this->sk)){
					$key = $this->getKeyBySk($k.'.'.$v);
					break;
				}
			}
		} elseif(!is_array($this->sk)) {
			$key = $this->getKeyBySk($sk);
		} else {
			return array();
		}

		$datas = $this->skcache?$this->redis->hGetAll($key):array();
		if(empty($datas)){
			$objs = parent::findBySk($sk, $param);
			$cache = array();
			foreach($objs as $obj)
			{
				$cache[$obj->PkKey] = $obj->Attributes;
			}
			if($this->skcache && $cache) {
				$this->redis->hMset($key, $cache);
			}
			if($this->pkcache && $cache) {
				$key = $this->getKeyByPk();
				$this->redis->hMset($key, $cache);
			}
		} else {
			$objs = array();
			$class = get_class($this);
			foreach($datas as $data)
			{
				foreach($param as $attr => $value)
				{
					if($data[$attr] != $value) continue 2;
				}
				$obj = new $class(false);
				$obj->_oldAttributes = $obj->_attributes = $data;
				$obj->init();
				$objs[$obj->PkKey] = $obj;
			}			
		}

		return $objs;
	}

	public function find($confition = array(), $param = array())
	{
		$obj = parent::find($confition, $param);
		if($this->pkcache && $obj) {
			$this->redis->hSet($this->KeyByPk, $obj->PkKey, $obj->Attributes);
		}
		return $obj;
	}

	public function findAll($confition = array(), $param = array())
	{
		$objs = parent::findAll($confition, $param);
		if($this->pkcache) {
			foreach($objs as $obj)
			{
				$this->redis->hSet($this->KeyByPk, $obj->PkKey, $obj->Attributes);
			}
		}
		return $objs;
	}

	public function save()
	{
		if($res = parent::save()){
			if($this->sk && $this->skcache){
				if(is_array($this->sk)){
					foreach($this->sk as $k){
						$skkey = $this->getKeyBySk($k.'.'.$this->_attributes[$k]);
						$this->redis->del($skkey);
					}
				} else {
					$skkey = $this->getKeyBySk($this->_attributes[$this->sk]);
					$this->redis->del($skkey);
				}
			}
			if($this->pkcache) {
				$pkkey = $this->getKeyByPk();
				$this->redis->hSet($pkkey, $this->PkKey, $this->_attributes);
			}
			$this->_oldAttributes = $this->_attributes;
		}
		return $res;
	}

	public function getKeyByPk()
	{
		return $this->Table.':Pk';
	}

	public function getKeyBySk($sk)
	{
		return $this->Table.':Sk:'.$sk;
	}

	public function getKeyByCond($cond, $param = array())
	{
		$fields = array();
		foreach($cond as $field => $value){
			if(array_key_exists($field, $this->_attributes)){
				$value = is_array($value)?implode(',', $value):$value;
				$fields[] = $field.'.'.$value;
			}
		}
		$keys = array();
		foreach(array('order', 'limit', 'offset') as $key){
			if(isset($param[$key])){
				$keys[] = $key.'.'.$param[$key];
			}
		}
		return $this->Table.':Field:'.implode(':',$fields).implode(':',$keys);
	}

	public function delete()
	{
		$cond = $this->_attributes;
		if($this->pkcache) {
			$pkkey = $this->getKeyByPk();
			$this->pkcache and $this->redis->hDel($pkkey, $this->PkKey);
		}
		if($this->sk && $this->skcache){
			if(is_array($this->sk)){
				foreach($this->sk as $k){
					$sk[] = $k.'.'.$cond[$k];
					$skkey = $this->getKeyBySk($k.'.'.$cond[$k]);
					$this->redis->hDel($skkey, $this->PkKey);
				}
			} else {
				$sk = $cond[$this->sk];
				$skkey = $this->getKeyBySk($sk);
				$this->redis->hDel($skkey, $this->PkKey);
			}
		}
		$this->pkcache = false;
		$this->skcache = false;
		return parent::delete();
	}

	protected function loadTable()
	{
		$attr = $this->redis->get($this->Table.':scheme');
		if(empty($attr)){
			$attr = parent::loadTable();
			$this->redis->set($this->Table.':scheme', $attr);
		}
		return $attr;
	}
}