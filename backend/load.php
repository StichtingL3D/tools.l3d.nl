<?php
class load {

private static $places;

public static function construct() {
	self::$places = json_decode(PLACES, true);
	
	// register helper() as autoloader
	spl_autoload_register('load::file');
}

public static function file($filename, $preference=false) {
	if (preg_match('/[^a-z0-9_]/', $filename)) {
		throw new Exception('no valid filename for inclusion (use a-z0-9_): '.$filename);
	}
	
	$types = array(
		'helpers',
		'models',
		'cronjobs',
	);
	#TODO: remove, now supports deprecated usage of models
	if ($preference) {
		array_unshift($types, $preference);
	}
	
	foreach ($types as $type) {
		$path = self::$places['backend'].$type.'/'.$filename.'.php';
		if (file_exists($path)) {
			require_once($path);
			return;
		}
	}
	
	// let swift use its own autoloader
	if (strpos($filename, 'Swift_') === 0) {
		return false;
	}
	throw new Exception('file not found, looked for '.$filename.'.php at helpers, models and cronjobs');
}

public static function helper($file) {
	return load::file($file);
}
public static function model($file, $id=false) {
	load::file($file, $preference='models');
	
	#TODO: remove, now supports deprecated usage of models
	if (class_exists($file.'_model', $autoload=false)) {
		$classname = $file.'_model';
		return new $classname($id);
	}
	
	return new $file($id);
}
public static function cronjob($filename) {
	return load::file($file);
}

public static function redirect($location) {
	header('Location: '.self::$places['www'].$location);
	exit;
}

public static function maintenance() {
	include_once(self::$places['public'].'error_maintenance.php');
	exit;
}

public static function error_404($e=false, $skip_mail=false, $url_vars=false) {
	if ($skip_mail) {
		define('SKIP_404_EMAIL', true);
	}
	
	include_once(self::$places['public'].'error_404.php');
	exit;
}

public static function error_500($e=false, $skip_mail=false) {
	if ($skip_mail) {
		define('SKIP_500_EMAIL', true);
	}
	
	// $e should preferably be an exception, but error-strings are also allowed
	include_once(self::$places['public'].'error_500.php');
	exit;
}

}
load::construct();
?>