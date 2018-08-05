<?php

namespace FastAR;

include_once 'RModel.php';

class YModel extends RModel
{
	private $obj = array();
	private $objAll = array();
	protected $saveP = 0;

	public function findByPk($pk, $param = array())
	{
		$pkKey = $this->getPkKey($pk);
		if(!isset($this->obj[$pkKey])){
			$this->obj[$pkKey] = parent::findByPk($pk, $param);
		}
		return $this->obj[$pkKey];
	}

	public function findBySk($sk, $param = array())
	{
		if(is_array($sk) && is_array($this->sk)){
			$key = $this->getKeyByCond($sk);
		} elseif (!is_array($this->sk)){
			$key = $this->getKeyByCond(array($this->sk => $sk));
		} else {
			return array();
		}
		if(!isset($this->objAll[$key])){
			$this->objAll[$key] = parent::findBySk($sk, $param);
			foreach($this->objAll[$key] as $obj){
				$pkKey = $this->getPkKey($obj->Attributes);
				$this->obj[$pkKey] = $obj;
			}
		}
		return $this->objAll[$key];
	}

	public function find($confition = array(), $param = array())
	{
		if($obj = parent::find($confition, $param)) {
			$pkKey = $this->getPkKey($obj->Attributes);
			$this->obj[$pkKey] = $obj;
			return $this->obj[$pkKey];
		} else {
			return null;
		}
	}

	public function findAll($confition = array(), $param = array())
	{
		$key = $this->getKeyByCond($confition, $param);
		if(!isset($this->objAll[$key])){
			$this->objAll[$key] = parent::findAll($confition, $param);
			foreach($this->objAll[$key] as $obj){
				$pkKey = $this->getPkKey($obj->Attributes);
				$this->obj[$pkKey] = $obj;
			}
		}
		return $this->objAll[$key];
	}

	public function delete()
	{
		$model = self::m();
		$pkKey = $this->getPkKey($this->_attributes);
		unset($model->obj[$pkKey]);
		foreach($model->objAll as $obj){
			unset($obj[$pkKey]);
		}
		return parent::delete();
	}

	protected function loadTable()
	{
		$model = self::m();
		if(empty($model->_attributes)){
			$model->_attributes = parent::loadTable();
		}
		return $model->_attributes;
	}

	public function pSave()
	{
		if(mt_rand(0, 99) < $this->saveP){
			return $this->save();
		}
		return false;
	}

	public function getKeyByCond($cond, $param = array())
	{
		$fields = array();
		foreach($this->_attributes as $field => $value){
			if(isset($cond[$field])){
				$value = is_array($cond[$field])?implode(',', $cond[$field]):$cond[$field];
				$fields[] = $field.'.'.$value;
			}
		}
		$keys = array();
		foreach(array('order', 'limit', 'offset', 'group') as $key){
			if(isset($param[$key])){
				$keys[] = $key.'.'.$param[$key];
			}
		}
		return implode(':',$fields).implode(':',$keys);
	}

}