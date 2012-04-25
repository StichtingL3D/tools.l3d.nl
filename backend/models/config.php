<?php
/*------------------------------------------------------------------------------
	configuration file
	
	$config = new config('mysql');
	$config->host
	$config['host']
	
	TODO:
	- update values
	- add/remove values?
------------------------------------------------------------------------------*/

class config implements ArrayAccess {

private $data;

public function __construct($category, $file='config') {
	$this->load_config($category, $file);
}

public function load_config($category, $file='config') {
	$places = json_decode(PLACES, true);
	$path = $places['data'].$category.'/'.$file.'.ini';
	if (file_exists($path) == false) {
		throw new Exception('config not found, looked at '.$category.'/'.$file.'.ini');
	}
	
	$this->data = parse_ini_file($path, $sections=true);
}

public function get_property($key) {
	if (!isset($this->data[$key])) {
		throw new Exception('config key does not exist');
	}
	return $this->data[$key];
}

public function get_as_array() {
	return $this->data;
}

/*------------------------------------------------------------------------------
	easy access to $config->host and $config['host']
------------------------------------------------------------------------------*/
public function __get($key) {
	return $this->get_property($key);
}
public function offsetGet($key) {
	// php doesn't handle offsetExists when using ArrayAccess for multidimensional arrays
	// using isset on multidimensional keys calls offsetGet instead of offsetExists
	// see http://stackoverflow.com/questions/2881431/arrayaccess-multidimensional-unset/2881533#2881533
	// and https://bugs.php.net/bug.php?id=32983
	// and http://www.ozzu.com/programming-forum/arrayaccess-interface-and-multi-dimensional-arrays-t89602.html
	// solution: check if the key exists inside offsetGet to prevent unnecessary errors
	if (isset($this->data[$key]) == false) {
		return null;
	}
	
	return $this->get_property($key);
}

public function __isset($key) {
	return isset($this->data[$key]);
}
public function offsetExists($key) {
	return isset($this->data[$key]);
}

public function __set($key, $new_value) {
	throw new Exception('can not update config files', 500);
}
public function offsetSet($offset, $value) {
	throw new Exception('can not update config files', 500);
}

public function __unset($key) {
	throw new Exception('can not update config files', 500);
}
public function offsetUnset($key) {
	throw new Exception('can not update config files', 500);
}

}
