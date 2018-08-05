<?php

namespace FastAR;

class Config
{
	private static $m = array();
	private static $path = CONF_DIR;

	public static function getConfig($file = null)
	{
		if($file === null)
			$file = 'default';
		$fileSys = explode('.', $file);
		$fileName = $fileSys[0];
		if(!isset(self::$m[$fileName])){
			self::load($fileName);
		}
		$config = self::$m;
		foreach($fileSys as $file)
		{
			if($file != 'default' && isset($config[$file]) && isset($config['default'])){
				$config = $config[$file] + $config['default'];
			} elseif(isset($config[$file])){
				$config = $config[$file];
			} elseif(isset($config['default'])) {
				$config = $config['default'];
			}
		}
		return $config;
	}

	private static function load($file)
	{
		$fileName = self::$path.$file.'.php';
		if(!is_file($fileName)){
			throw new \Exception("Cannot find file: '{$fileName}'", 500);
		}
		self::$m[$file] = include($fileName);
	}
}