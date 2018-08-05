<?php
include_once '../fastar/ar/YModel.php';
include_once '../fastar/ar/RModel.php';
include_once '../fastar/ar/Model.php';
include_once '../fastar/ar/Config.php';
include_once '../fastar/ar/DB.php';
include_once '../fastar/ar/NoDB.php';

define("CONF_DIR", '../fastar/config'.DIRECTORY_SEPARATOR);

use FastAR\YModel;

class Student extends YModel
{
	protected $pk = 'id';
	protected $sk;

	protected $pkcache = true;
	protected $skcache = false;
}