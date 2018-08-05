<?php
return array(
	'v' => 1,
	'default' => array(
		'host' => '127.0.0.1',
		'port' => '6379',
		'prev' => 'jDefault',
		'timeOut' => 86400,
		'flag' => '0',
	),
	'app' => array(
		'prev' => 'jApp',
		// 'timeOut' => 60,
		'flag' => '1',
	),
	'table' => array(
		'prev' => 'jTable',
		'timeOut' => 86400,
		'flag' => '2',
	),
	'log' => array(
		'prev' => 'jLog',
		'timeOut' => 2592000,
		'flag' => '3',
	),
	'config' => array(
		'prev' => 'jConfig',
		'flag' => '4',
		//'timeOut' => 5,
	),
	'uc' => array(
		'prev' => 'jUc',
		'flag' => '5',
	),
);
