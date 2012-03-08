<?php
/*------------------------------------------------------------------------------
	mysql - the basics of querying
	
	use select($sql, $argument1, $argument2, ..) for select statements,
		or query($sql, $argument1, $argument2, ..) for all other statements
		pass values as arguments to auto-escape them
		manually escape values using escape($value)
	
	error codes on:
		http://dev.mysql.com/doc/refman/5.1/en/error-messages-server.html (1000+)
		http://dev.mysql.com/doc/refman/5.1/en/error-messages-client.html (2000+)
------------------------------------------------------------------------------*/

class mysql {

public static $connection = false;

// these are updated after each query
public static $latest_query;
public static $all_queries;
public static $errno;
public static $error;
public static $num_rows;
public static $insert_id;
public static $affected_rows;

// pass values as arguments to auto-escape them
public static function query($sql) {
	if (func_num_args() > 1) {
		$func_args = func_get_args();
		unset($func_args[0]); // $sql
		$sql = self::escape_and_merge($sql, $func_args);
	}
	
	return self::query_raw($sql);
}

// $type = field | row | array
// pass values as arguments to auto-escape them
public static function select($type, $sql) {
	if (func_num_args() > 1) {
		$func_args = func_get_args();
		unset($func_args[0]); // $type
		unset($func_args[1]); // $sql
		$sql = self::escape_and_merge($sql, $func_args);
	}
	
	$result = self::query_raw($sql);
	
	// format the result in the right way
	if (self::$errno) {
		return false;
	}
	elseif ($type == 'field') {
		$row = $result->fetch_array();
		$field = $row[0];
		return $field;
	}
	elseif ($type == 'row') {
		$row = $result->fetch_assoc();
		return $row;
	}
	else { // array
		$array = array();
		while ($row = $result->fetch_assoc()) {
			if (isset($row['id'])) {
				$array[$row['id']] = $row;
			}
			else {
				$array[] = $row;
			}
		}
		return $array;
	}
}

/*------------------------------------------------------------------------------
	internal functions, some are allowed (public)
------------------------------------------------------------------------------*/
public static function escape($value) {
	if (self::$connection == false) {
		throw new Exception('no mysql db connection', 500);
	}
	
	if (is_array($value)) {
		throw new Exception('arguments for query() or select() should be values, not an array');
	}
	return self::$connection->real_escape_string($value);
}

private static function escape_and_merge($sql, $args) {
	// escape ..
	foreach ($args as &$arg) {
		$arg = self::escape($arg);
	}
	
	// sprintf's x and X modifiers are not utf-8 safe, don't use them
	if (strpos($sql, '%x') || strpos($sql, '%X')) {
		throw new Exception('can\'t use non-safe utf-8 x/X modifiers for vsprintf');
	}
	
	// .. & merge
	$sql = vsprintf($sql, $args);
	return $sql;
}

private static function query_raw($sql) {
	if (self::$connection == false) {
		throw new Exception('no mysql db connection', 500);
	}
	
	// secure too generic update and delete queries
	if (strpos($sql, 'UPDATE') === 0 || strpos($sql, 'DELETE') === 0) {
		// update and delete queries must have an explicit where or limit statement
		if (strpos($sql, 'WHERE') === false && strpos($sql, 'LIMIT') === false) {
			throw new Exception('insecure update/delete, use a where or a limit');
		}
	}
	
	self::$latest_query = $sql;
	self::$all_queries[] = self::$latest_query;
	
	$result = self::$connection->query($sql);
	
	self::$errno = self::$connection->errno;
	self::$error = self::$connection->error;
	if (self::$errno && ENVIRONMENT == 'development') {
		$e = new Exception('error in mysql query: '.self::$error, self::$errno);
		error::mail($e, false, $type='mysql');
		throw $e;
	}
	elseif (self::$errno) {
		$e = new Exception('error in mysql query: '.self::$error, self::$errno);
		error::mail($e, false, $type='mysql');
		$e = false;
		
		throw new Exception('error in mysql query');
	}
	
	if (strpos($sql, 'SELECT') === 0) {
		self::$num_rows = $result->num_rows;
	}
	else {
		self::$num_rows = false;
	}
	
	if (strpos($sql, 'INSERT') === 0) {
		self::$insert_id = self::$connection->insert_id;
	}
	else {
		self::$insert_id = false;
	}
	
	// check affected_rows first
	// sometimes we get an apache segfault when requesting affected_rows without a connection
	// tested on php 5.2.10, apache 2(20051115), mysqli 5.1.34
	// fixed in php 5.2.13 (https://bugs.php.net/50727)
	if (isset(self::$connection->affected_rows) == true) {
		self::$affected_rows = self::$connection->affected_rows;
	}
	else {
		self::$affected_rows = false;
	}
	
	return $result;
}

public static function construct() {
	// security: turn off magic quotes for files and databases, deprecated as of PHP 6
	if (version_compare(PHP_VERSION, '5.3', '<')) {
		ini_set('magic_quotes_runtime', 0);
	}
	
	/*--- connect to the database ---*/
	$config = load::config('mysql');
	
	try {
		$connection = new mysqli($config['host'], $config['user'], base64_decode($config['pass']), $config['name']);
	}
	catch (Exception $e) {
		error::mail($e, false, $type='mysql');
		throw $e;
	}
	
	if (version_compare(PHP_VERSION, '5.2.9') >= 0 && isset($connection) && $connection->connect_error) {
		self::$errno = $connection->connect_errno;
		self::$error = $connection->connect_error;
	}
	elseif (mysqli_connect_error()) {
		self::$errno = mysqli_connect_errno();
		self::$error = mysqli_connect_error();
	}
	
	if (self::$errno) {
		if (self::$errno == '1045') {
			$e = new Exception('failed to connect to mysql db: check username/password combination', self::$errno);
		}
		if (self::$errno == '1049') {
			$e = new Exception('failed to connect to mysql db: create the database (or fix the settings)', self::$errno);
		}
		if (self::$errno >= 2000) {
			$e = new Exception('failed to connect to mysql db: start mysql (or fix the settings)', self::$errno);
		}
		error::mail($e, false, $type='mysql');
		throw $e;
	}
	
	self::$connection = $connection;
	
	self::query_raw("SET NAMES utf8;");
	self::query_raw("SET CHARACTER SET utf8;");
}

}
mysql::construct();
?>