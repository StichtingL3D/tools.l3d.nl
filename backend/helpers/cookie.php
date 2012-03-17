<?php
/*------------------------------------------------------------------------------
	cookie - wrapper for php's cookie management
------------------------------------------------------------------------------*/

class cookie {

public static function get($name) {
	if (empty($_COOKIE[$name])) {
		return false;
	}
	
	$value = $_COOKIE[$name];
	if (strpos($value, '[') === 0 || strpos($value, '{') === 0) {
		$value = json_decode($value, true);
	}
	
	return $value;
}

public static function set($name, $value, $expire=false, $path=false) {
	if (is_array($value)) {
		$value = json_encode($value);
	}
	
	if ($expire == 'week') {
		$expire = time()+60*60*24*7;
	}
	
	$path = ($path == false) ? '/' : $path;
	$domain = '.'.APP_DOMAIN;
	$secure_https = false;
	$http_only = true;
	
	setcookie($name, $value, $expire, $path, $domain, $secure_https, $http_only);
}

public static function remove($name, $path=false) {
	$expire = time() - 60*60*24*365; // one year ago
	$path = ($path == false) ? '/' : $path;
	$domain = '.'.APP_DOMAIN;
	$secure_https = false;
	$http_only = true;
	
	setcookie($name, "", $expire, $path, $domain, $secure_https, $http_only);
}

}
?>