<?php

namespace FastAR;

include_once 'Base.php';

abstract class Model extends Base implements \IteratorAggregate, \ArrayAccess
{
	private static $models = array();
	protected $mysql;
	protected $_attributes = array();
	protected $_oldAttributes = array();
	protected $pk;
	protected $sk;
	public $isNew = true;

	public function __construct($isNew = true)
	{
		if($this->mysql == null){
			$mysqlConfig = Config::getConfig('mysql.table');
			$this->mysql = DB::getInstance($mysqlConfig);
		}
		if($isNew){
			$this->_attributes = $this->loadTable();
		}
		$this->isNew = $isNew;
	}

	public static function m()
	{
		$className = get_called_class();
		if(!isset(self::$models[$className])){
			self::$models[$className] = new $className(false);
			self::$models[$className]->loadTable();
		}
		return self::$models[$className];
	}

	public function __get($name)
	{
		if(array_key_exists($name, $this->_attributes)){
			return $this->_attributes[$name];
		} else {
			return parent::__get($name);
		}
	}

	public function __set($name, $value)
	{
		if(array_key_exists($name, $this->_attributes)) {
			$this->_attributes[$name] = $value;
		} else {
			parent::__set($name, $value);
		}
	}

	public function __isset($name)
	{
		if(array_key_exists($name, $this->_attributes)){
			return true;
		} else {
			return parent::__isset($name);
		}
	}

	public function __unset($name)
	{
		if(array_key_exists($name, $this->_attributes)){
			unset($this->_attributes[$name]);
		} else {
			parent::__unset($name);
		}
	}

	public function __toString()
	{
		return json_encode($this->_attributes);
	}

	protected function init()
	{

	}

	public function setAttributes($data)
	{
		foreach($data as $key => $value)
		{
			if(array_key_exists($key, $this->_attributes)) {
				$this->_attributes[$key] = $value;
			}
		}
	}

	public function getAttributes()
	{
		return $this->_attributes;
	}

	public function getOldAttributes()
	{
		return $this->_oldAttributes;
	}

	public function getTable()
	{
		return lcfirst(get_class($this));
	}

	public function findByPk($pk, $param = array())
	{
		$confition = array();
		if(is_array($this->pk)){
			foreach ($this->pk as $key) {
				if(!array_key_exists($key, $pk)){
					return null;
				}
				$confition[$key] = $pk[$key];
			}
		} else {
			$confition[$this->pk] = $pk;
		}
		return $this->find($confition, $param);
	}

	public function findBySk($sk, $param = array())
	{
		$confition = array();
		if(is_array($sk) && is_array($this->sk)){
			foreach ($sk as $key => $value) {
				$confition[$key] = $value;
			}
		} elseif (!is_array($this->sk)){
			$confition[$this->sk] = $sk;
		} else {
			return array();
		}
		return $this->findAll($confition, $param);
	}

	public function save()
	{
		if (array_diff_assoc($this->_attributes, $this->_oldAttributes) === array()) {
			return 0;
		}

		if(array_key_exists('updateTime', $this->_attributes)){
			$this->_attributes['updateTime'] = App::T();
		}

		if($this->isNew) {
			if(array_key_exists('createTime', $this->_attributes)){
				$this->_attributes['createTime'] = App::T();
			}
			$res = $this->insert($this->_attributes);
			$this->isNew = false;
		} else {
			$confition = array();
			if(is_array($this->pk)){
				foreach ($this->pk as $key) {
					$confition[$key] = $this->_oldAttributes[$key];
				}
			} else {
				$confition[$this->pk] = $this->_oldAttributes[$this->pk];
			}
			$res = $this->update($this->_attributes, $confition);
		}
		if($res){
			$this->_oldAttributes = $this->_attributes;
		}
		
		return $res;
	}

	public function find($confition = array(), $param = array())
	{
		if(array_key_exists('deleteFlag', $this->_attributes)){
			$confition['deleteFlag'] = 0;
		}
		$data = $this->findData($confition, $param);
		if($data){
			$class = get_class($this);
			$model = new $class(false);
			$model->_oldAttributes = $model->_attributes = $data;
			$model->init();
			return $model;
		} else {
			return false;
		}
	}

	public function findAll($confition = array(), $param = array())
	{
		if(array_key_exists('deleteFlag', $this->_attributes)){
			$confition['deleteFlag'] = 0;
		}
		$datas = $this->findDatas($confition, $param);
		$models = array();
		$class = get_class($this);
		foreach($datas as $data)
		{
			$model = new $class(false);
			$model->_oldAttributes = $model->_attributes = $data;
			$model->init();
			$models[$model->PkKey] = $model;
		}
		return $models;
	}

	public function findData($confition = array(), $param = array())
	{
		$param['from'] = $this->Table;
		return $this->mysql->find($confition, $param);
	}

	public function findDatas($confition = array(), $param = array())
	{
		$param['from'] = $this->Table;
		return $this->mysql->findAll($confition, $param);
	}

	public function insert($data)
	{
		$res = $this->mysql->insert($this->Table, $data);
		if($res){
			$lastId = $this->mysql->lastInsertId();
			if(!is_array($this->pk) && $lastId){
				$this->_attributes[$this->pk] = $lastId;
			}
		}
		return $res;
	}

	public function update($data, $confition)
	{
		return $this->mysql->update($this->Table, $data, $confition);
	}

	public function delete()
	{
		if(array_key_exists('deleteFlag', $this->_attributes)){
			$this->deleteFlag = 1;
			return $this->save();
		} else {
			$confition = array();
			if(is_array($this->pk)){
				foreach ($this->pk as $key) {
					$confition[$key] = $this->_oldAttributes[$key];
				}
			} else {
				$confition[$this->pk] = $this->_oldAttributes[$this->pk];
			}
			return $this->mysql->delete($this->Table, $confition);
		}
	}

	public function deleteData($confition = array())
	{
		return $this->mysql->delete($this->Table, $confition);
	}

	public function count($confition = array(), $param = array())
	{
		if(array_key_exists('deleteFlag', $this->_attributes)){
			$confition['deleteFlag'] = 0;
		}
		$param['select'] = 'count(*) as n';
		$data = $this->findData($confition, $param);
		return $data['n'];
	}

	protected function getPkKey($pk = null)
	{
		$pkKey = '';
		if(is_array($this->pk)){
			$pkArr = array();
			foreach ($this->pk as $key) {
				if(is_array($pk)){
					$pkArr[] = $pk[$key];
				} else {
					$pkArr[] = $this[$key];
				}
			}
			$pkKey = implode(':', $pkArr);
		} else {
			if(is_array($pk)){
				$pkKey = $pk[$this->pk];
			} elseif($pk == null) {
				$pkKey = $this[$this->pk];
			} else {
				$pkKey = $pk;
			}
		}
		return $pkKey;
	}

	protected function exec($sql)
	{
		return $this->mysql->exec($sql);
	}

	protected function loadTable()
	{
		$sql = 'SHOW FULL COLUMNS FROM `'.$this->Table.'`';
		$columns = $this->mysql->query($sql);
		foreach($columns as $column){
			$name = $column['Field'];
			$this->_attributes[$name] = $column['Default'];
		}
		//debug(get_called_class(), 'loadTable');
		return $this->_attributes;
	}

	public function getIterator()
	{
		$attributes = $this->Attributes;
		return new ArrayIterator($attributes);
	}

	public function offsetExists($offset)
	{
		return property_exists($this,$offset);
	}

	public function offsetGet($offset)
	{
		return $this->$offset;
	}

	public function offsetSet($offset,$item)
	{
		$this->$offset=$item;
	}

	public function offsetUnset($offset)
	{
		unset($this->$offset);
	}

	public function getShowList()
	{
		return array();
	}
}