<?php
class load {

private static $places;

public static function construct() {
	self::$places = json_decode(PLACES, true);
	
	// register helper() as autoloader
	spl_autoload_register('load::helper');
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

public static function helper($file) {
	// let swift use its own autoloader
	if (strpos($file, 'Swift_') === 0) {
		return false;
	}
	
	$path = self::$places['backend'].'helpers/'.$file.'.php';
	if (file_exists($path) == false) {
		throw new Exception('helper not found, looked at helpers/'.$file.'.php');
	}
	require_once($path);
}

public static function model($file, $id=false) {
	$plural = (substr($file, -1) == 's') ? true : false;
	$base_model = ($plural) ? 'models' : 'model';
	require_once(self::$places['backend'].'models/'.$base_model.'.php');
	
	$path = self::$places['backend'].'models/'.$file.'.php';
	if (file_exists($path) == false) {
		throw new Exception('model not found, looked at models/'.$file.'.php');
	}
	require_once($path);
	$class = $file.'_model';
	
	if (func_num_args() == 2) {
		$model = new $class($id);
	}
	else {
		// start the class with an unknown amount of variables
		// thanks zerkms or troelskn on stack overflow:
		// http://stackoverflow.com/questions/4708822/how-to-initialize-objects-with-unknown-arguments
		// http://stackoverflow.com/questions/779898/passing-arguments-to-the-class-constructor
		$constuct_args = func_get_args();
		unset($constuct_args[0]); // remove the $file arg
		
		$class_instance = new ReflectionClass($class);
		$model = $class_instance->newInstanceArgs($constuct_args); // equal to 'new'
	}
	
	return $model;
}

public static function relation_model($table, $elements, $child=false) {
	require_once(self::$places['backend'].'models/relations.php');
	
	if ($child) {
		$child_path = self::$places['backend'].'models/'.$file.'.php';
		if (file_exists($child_path) == false) {
			throw new Exception('relation child model not found, looked at models/'.$file.'.php');
		}
		require_once($child_path);
		$model = new $child($table, $elements);
	}
	else {
		$model = new relations_model($table, $elements);
	}
	
	return $model;
}

public static function template($file, $ext='.html') {
	$path = self::$places['backend'].'templates/'.$file.$ext;
	if (file_exists($path) == false) {
		throw new Exception('template not found, looked at templates/'.$file.$ext);
	}
	return file_get_contents($path);
}

public static function cronjob($filename) {
	$path = self::$places['backend'].'cronjobs/'.$filename.'.php';
	if (file_exists($path) == false) {
		throw new Exception('cronjob not found, looked at cronjobs/'.$filename.'.php');
	}
	
	require_once($path);
}

public static function config($part, $file='config') {
	$path = self::$places['data'].$part.'/'.$file.'.ini';
	if (file_exists($path) == false) {
		throw new Exception('config not found, looked at '.$part.'/'.$file.'.ini');
	}
	return parse_ini_file($path, $sections=true);
}

public static function cache($part, $file='config') {
	$path = self::$places['data'].$part.'/'.$file.'.json';
	if (file_exists($path) == false) {
		throw new Exception('cache not found, looked at '.$part.'/'.$file.'.json');
	}
	return json_decode(file_get_contents($path), true);
}

}
load::construct();
?>