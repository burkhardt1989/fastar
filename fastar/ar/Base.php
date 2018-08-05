<?php

namespace FastAR;

abstract class Base
{
	public function __get($name)
	{
		$method = 'get'.$name;
		if(method_exists($this, $method)){
			return $this->$method();
		} else {
			throw new Exception("can't get, class ".__CLASS__." have no ".$name, 500);
		}
	}

	public function __set($name, $value)
	{
		$method = 'set'.$name;
		if(method_exists($this, $method)){
			$this->$method($value);
		} else {
			throw new Exception("can't set, class ".__CLASS__." have no ".$name, 500);
		}
	}

	public function __isset($name)
	{
		$method='get'.$name;
		if(method_exists($this,$method))
			return $this->$method()!==null;
		else
			return false;
	}

	public function __unset($name)
	{
		$method='set'.$name;
		if(method_exists($this,$method))
			$this->$method(null);
		elseif(method_exists($this,'get'.$name))
			throw new Exception($name.' is read only in class '.get_class($this).'.');
	}


}