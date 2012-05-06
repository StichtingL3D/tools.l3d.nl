<?php
/*------------------------------------------------------------------------------
	base model for db tables
------------------------------------------------------------------------------*/

class table {

public $error = false;

public $id = null;
protected $id_isint = true;
protected $index_key = 'id';
private $id_regex = "%d";

protected $table; // defaults to class name
protected $table_data;
protected $table_changes;

public function __construct($id=null) {
	// sensible defaults
	if (is_null($this->table)) {
		$this->table = get_class($this);
		if (substr($this->table, -1) != 's') {
			$this->table .= 's';
		}
	}
	
	// hatch onto a table
	$this->db_connect();
	
	// singular objects
	if (!is_null($id)) {
		if (empty($id)) {
			throw new Exception('no valid id for '.$this->table.': *empty*');
		}
		if ($this->id_isint && (!is_numeric($id) || $id <= 0)) {
			throw new Exception('no valid id for '.$this->table.': '.$id);
		}
		
		// some ids are strings (like session-ids)
		if ($this->id_isint == false) {
			$this->id_regex = "'%s'";
		}
		
		// load the initial object from the db
		$this->id = $id;
		$this->get_object();
	}
}

/*------------------------------------------------------------------------------
	selecting singular
------------------------------------------------------------------------------*/
public function get_property($key) {
	if (is_null($this->id)) {
		throw new Exception('can not use get_property() on plural objects');
	}
	if (array_key_exists($key, $this->table_data) == false) {
		// use array_key_exists() as value could be NULL
		throw new Exception('key "'.$key.'" doesn\'t exist in model('.$this->table.')');
	}
	
	return $this->table_data[$key];
}

public function get_object() {
	if (is_null($this->id)) {
		throw new Exception('can not use get_object() on plural objects');
	}
	
	if (empty($this->table_data)) {
		$sql = "SELECT * FROM `".$this->table."` WHERE `".$this->index_key."` = ".$this->id_regex.";";
		$this->table_data = $this->db_select('row', $sql, $this->id);
		
		if ($this->table_data == false) {
			throw new Exception('empty result set ('.$this->table.')');
		}
	}
	
	return $this->table_data;
}

/*--- shortcuts ---*/

public function __get($key) {
	return $this->get_property($key);
}

public function __isset($key) {
	$value = $this->get_property($key);
	return isset($value);
}

/*------------------------------------------------------------------------------
	selecting plural
------------------------------------------------------------------------------*/

/*------------------------------------------------------------------------------
	selecting multiple objects
	
	- $keys		string or array with asked key(s)
					"title" or array("title", "text")
	- $where	an array containing where statements
					array("color", "blue") or array(array("color" => "blue"), array("id", "<", "5"))
	- $group	a string with the key name to group by
	- $order	a string with the key name to order by
					defaults to ASC, prepend with - for DESC
	- $limit	an integer limiting the amount of results in the set, defaults to 100
------------------------------------------------------------------------------*/
public function select($keys=false, $where=false, $group=false, $order=false, $limit=100) {
	if (!is_null($this->id)) {
		throw new Exception('can not use select() on singular objects');
	}
	
	$sql_args = array();
	
	// add `%s` for all keys and add the keys to the args array
	$key_str = "*";
	if ($keys != false && $keys != "*") {
		if (!is_array($keys)) {
			$keys = array($keys);
		}
		$key_str = mysql_builder::keys($keys);
		$sql_args += $keys;
	}
	
	// simple filter using where statements
	$where_str = "";
	if ($where && is_array($where)) {
		$where_str = "WHERE ".mysql_builder::where($where, $sql_args);
	}
	
	// grouping
	$group_str = "";
	if ($group && !is_array($group)) {
		$group_str = "GROUP BY `%s`";
		$sql_args[] = $group;
	}
	
	// ordering
	$order_str = "";
	if ($order && !is_array($order)) {
		$order_str = "ORDER BY `%s`";
		
		// descending
		if (strpos($order, '-') === 0) {
			$order = substr($order, 1);
			$order_str .= " DESC";
		}
		
		$sql_args[] = $order;
	}
	
	// limiting the amount
	$limit_str = "";
	if ($limit && is_int($limit)) {
		$limit_str = "LIMIT ".$limit;
	}
	
	// combining everything
	$sql = "SELECT ".$key_str." FROM `".$this->table."` ".$where_str." ".$group_str." ".$order_str." ".$limit_str.";";
	array_unshift($sql_args, 'array', $sql);
	try {
		$all = call_user_func_array(array($this, 'db_select'), $sql_args);
	}
	catch (Exception $e) {
		$this->error = $this->db_error;
		$all = array();
	}
	
	// nicify the result for single-key selects (i.e. "select keyname from")
	if ($keys != false && $keys != "*" && count($keys) === 1) {
		foreach ($all as &$row) {
			// pick the first (and single) value from the result
			$row = current($row);
		}
	}
	
	return $all;
}


/*------------------------------------------------------------------------------
	count rows
	
	count all rows (default)
	- SELECT COUNT(*) FROM `table`
	
	count how often a value is used in a key
	- SELECT `key`, COUNT(*) FROM `table` GROUP BY `key`
	- SELECT `key`, COUNT(*) FROM `table` WHERE `key_2` = 'value' GROUP BY `key`
------------------------------------------------------------------------------*/
public function count($key=false, $where=false) {
	if (!is_null($this->id)) {
		throw new Exception('can not use count() on singular objects');
	}
	
	$sql_args = array();
	$sql = "SELECT";
	
	// count all rows, and possibly search for a special key
	if ($key) {
		$sql .= " `%s`,";
		$sql_args[] = $key;
	}
	$sql .= " COUNT(*)";
	
	// table
	$sql .= " FROM `".$this->table."`";
	
	// limit the count to special cases
	if ($where) {
		$sql .= " WHERE ".mysql_builder::where($where, $sql_args);
	}
	
	// grouping by the same key
	if ($key) {
		$sql .= " GROUP BY `%s`";
		$sql_args[] = $key;
	}
	
	// order also by this key
	if ($key) {
		$sql .= " ORDER BY `%s`";
		$sql_args[] = $key;
	}
	
	// end the sql and start counting
	$sql .= ";";
	if ($key) {
		array_unshift($sql_args, 'array', $sql);
		$result = call_user_func_array(array($this, 'db_select'), $sql_args);
		
		$count_array = array();
		foreach ($result as $result) {
			$index = reset($result);
			$count = end($result);
			$count_array[$index] = $count;
		}
		$count = $count_array;
	}
	else {
		array_unshift($sql_args, $sql);
		$result = call_user_func_array(array($this, 'db_query'), $sql_args);
		$count = $result->fetch_array();
		$count = $count[0];
	}
	
	return $count;
}

/*------------------------------------------------------------------------------
	updating singular
------------------------------------------------------------------------------*/
public function update_property($key, $new_value) {
	if (is_null($this->id)) {
		throw new Exception('can not use update_property() on plural objects');
	}
	
	// stage the new value for later writing
	$this->table_data[$key] = $new_value;
	$this->table_changes[$key] = $new_value;
}

// doesn't use the write cache ($this->table_changes) as it doesn't fit in a simple way
public function update_counter($key, $operator='+', $amount=1) {
	if (is_null($this->id)) {
		throw new Exception('can not use update_counter() on plural objects');
	}
	if (is_int($this->table_data[$key]) == false) {
		throw new Exception('can not update counter on non-int field');
	}
	
	$operator = ($operator == '+') ? '+' : '-';
	
	$sql = "UPDATE `".$this->table."` SET `%s` = `%s` ".$operator." %d WHERE `".$this->index_key."` = ".$this->id_regex.";";
	$this->db_query($sql, $key, $key, $amount, $this->id);
	
	// don't update the cache: it is too expensive for a unimportant counter
}

/*--- shortcuts ---*/

public function __set($key, $new_value) {
	return $this->update_property($key, $new_value);
}

public function __unset($key) {
	throw new Exception('can not unset table fields', 500);
}

public function empty_property($key) {
	return $this->update_property($key, '');
}

public function increase_counter($key, $amount=1) {
	return $this->update_counter($key, '+', $amount);
}

public function decrease_counter($key, $amount=1) {
	return $this->update_counter($key, '-', $amount);
}

/*------------------------------------------------------------------------------
	inserting
	when used in a singular object, the model switches to the new object
------------------------------------------------------------------------------*/

/*------------------------------------------------------------------------------
	insert a new row
------------------------------------------------------------------------------*/
protected function insert($new_data, $replace=false) {
	$sql = ($replace) ? "REPLACE" : "INSERT";
	$sql .= " INTO `".$this->table."` SET ";
	$sql_args = array();
	
	foreach ($new_data as $key => $value) {
		$sql .= "`%s` = ";
		
		if (is_int($value) && $value[0] !== '0') {
			// only for integer values
			// and not starting with zero, as that would be lost
			$sql .= "%d,";
		}
		else {
			$sql .= "'%s',";
		}
		
		$sql_args[] = $key;
		$sql_args[] = $value;
	}
	$sql = rtrim($sql, ",");
	$sql .= ";";
	
	array_unshift($sql_args, $sql);
	call_user_func_array(array($this, 'db_query'), $sql_args);
	
	// switch the model to the new object, and fetch its data from the db
	$this->id = $this->db_insert_id;
	$this->get_object();
	
	return $this->id;
}

/*------------------------------------------------------------------------------
	closing the model
	purge the updated keys
------------------------------------------------------------------------------*/
public function __destruct() {
	// write changes to the database
	if (!is_null($this->id)) {
		try {
			$this->update_object();
		}
		catch (Exception $e) {
			#TODO: find something better for this -- if error::mail throws an exception we get an endless loop
			// email the webmasters as we're not allowed to throw exceptions during destruct
			try {
				error::mail($e);
			}
			catch (Exception $e) {
				// i said NOT allowed, skip silently
			}
		}
	}
}

protected function update_object() {
	if (is_null($this->id)) {
		return;
	}
	if (!is_array($this->table_changes) && count($this->table_changes) < 1) {
		return;
	}
	
	$sql = "UPDATE `".$this->table."` SET ";
	$mysql_args = array();
	
	// collect changes
	foreach ($this->table_changes as $key => $new_value) {
		$sql .= "`".$key."` = ";
		
		if (is_int($this->table_data[$key]) && is_int($new_value) && $new_value[0] !== '0') {
			// only for integer values
			// and not starting with zero, as that would be lost
			$sql .= "%d,";
		}
		else {
			$sql .= "'%s',";
		}
		
		$mysql_args[] = $new_value;
	}
	$sql = rtrim($sql, ",");
	
	// finish up
	$sql .= " WHERE `".$this->index_key."` = ".$this->id_regex.";";
	$mysql_args[] = $this->id;
	
	// write all updates to the database
	array_unshift($mysql_args, $sql);
	call_user_func_array(array($this, 'db_query'), $mysql_args);
}

/*------------------------------------------------------------------------------
	queriing mysql
------------------------------------------------------------------------------*/
private $db_connection = false;

// these are updated after each query
protected $db_latest_query;
protected $db_all_queries;
protected $db_errno;
protected $db_error;
protected $db_num_rows;
protected $db_insert_id;
protected $db_affected_rows;

// pass values as arguments to auto-escape them
protected function db_query($sql) {
	if (func_num_args() > 1) {
		$func_args = func_get_args();
		unset($func_args[0]); // $sql
		$sql = $this->db_escape_and_merge($sql, $func_args);
	}
	
	return $this->db_query_raw($sql);
}

// $type = field | row | array
// pass values as arguments to auto-escape them
protected function db_select($type, $sql) {
	if (func_num_args() > 1) {
		$func_args = func_get_args();
		unset($func_args[0]); // $type
		unset($func_args[1]); // $sql
		$sql = $this->db_escape_and_merge($sql, $func_args);
	}
	
	$result = $this->db_query_raw($sql);
	
	// format the result in the right way
	if ($this->db_errno) {
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
			$array[] = $row;
		}
		return $array;
	}
}

/*------------------------------------------------------------------------------
	mysql helpers
------------------------------------------------------------------------------*/
protected function db_escape($value) {
	if ($this->db_connection == false) {
		throw new Exception('no mysql db connection', 500);
	}
	
	if (is_array($value)) {
		throw new Exception('arguments for query() or select() should be values, not an array');
	}
	return $this->db_connection->real_escape_string($value);
}

private function db_escape_and_merge($sql, $args) {
	// escape ..
	foreach ($args as &$arg) {
		$arg = $this->db_escape($arg);
	}
	
	// sprintf's x and X modifiers are not utf-8 safe, don't use them
	if (strpos($sql, '%x') || strpos($sql, '%X')) {
		throw new Exception('can not use non-safe utf-8 x/X modifiers for vsprintf');
	}
	
	// .. & merge
	$sql = vsprintf($sql, $args);
	return $sql;
}

private function db_query_raw($sql) {
	if ($this->db_connection == false) {
		throw new Exception('no mysql db connection', 500);
	}
	
	// secure too generic update and delete queries
	if (strpos($sql, 'UPDATE') === 0 || strpos($sql, 'DELETE') === 0) {
		// update and delete queries must have an explicit where or limit statement
		if (strpos($sql, 'WHERE') === false && strpos($sql, 'LIMIT') === false) {
			throw new Exception('insecure update/delete, use a where or a limit');
		}
	}
	
	$this->db_latest_query = $sql;
	$this->db_all_queries[] = $this->db_latest_query;
	
	$result = $this->db_connection->query($sql);
	
	$this->db_errno = $this->db_connection->errno;
	$this->db_error = $this->db_connection->error;
	if ($this->db_errno && ENVIRONMENT == 'development') {
		$e = new Exception('error in mysql query: '.$this->db_error, $this->db_errno);
		error::mail($e, false, $type='mysql');
		throw $e;
	}
	elseif ($this->db_errno) {
		$e = new Exception('error in mysql query: '.$this->db_error, $this->db_errno);
		error::mail($e, false, $type='mysql');
		$e = false;
		
		throw new Exception('error in mysql query');
	}
	
	if (strpos($sql, 'SELECT') === 0) {
		$this->db_num_rows = $result->num_rows;
	}
	else {
		$this->db_num_rows = false;
	}
	
	if (strpos($sql, 'INSERT') === 0) {
		$this->db_insert_id = $this->db_connection->insert_id;
	}
	else {
		$this->db_insert_id = false;
	}
	
	// check affected_rows first
	// sometimes we get an apache segfault when requesting affected_rows without a connection
	// tested on php 5.2.10, apache 2(20051115), mysqli 5.1.34
	// fixed in php 5.2.13 (https://bugs.php.net/50727)
	if (isset($this->db_connection->affected_rows) == true) {
		$this->db_affected_rows = $this->db_connection->affected_rows;
	}
	else {
		$this->db_affected_rows = false;
	}
	
	return $result;
}

/*------------------------------------------------------------------------------
	connect to mysql
------------------------------------------------------------------------------*/
protected function db_config() {
	return new config('mysql');
}

protected function db_switch($db_name) {
	$this->connection->select_db($db_name);
}

private function db_connect() {
	// security: turn off magic quotes for files and databases, deprecated as of PHP 6
	if (version_compare(PHP_VERSION, '5.3', '<')) {
		ini_set('magic_quotes_runtime', 0);
	}
	
	/*--- connect to the database ---*/
	$config = $this->db_config();
	
	try {
		#echo 'connecting to '.$config['user'].'@'.$config['host'].NLd;
		$connection = new mysqli($config['host'], $config['user'], base64_decode($config['pass']), $config['name']);
	}
	catch (Exception $e) {
		error::mail($e, false, $type='mysql');
		throw $e;
	}
	
	if (version_compare(PHP_VERSION, '5.2.9') >= 0 && isset($connection) && $connection->connect_error) {
		$this->db_errno = $connection->connect_errno;
		$this->db_error = $connection->connect_error;
	}
	elseif (mysqli_connect_error()) {
		$this->db_errno = mysqli_connect_errno();
		$this->db_error = mysqli_connect_error();
	}
	
	if ($this->db_errno) {
		if ($this->db_errno == '1045') {
			$e = new Exception('failed to connect to mysql db: check username/password combination', $this->db_errno);
		}
		if ($this->db_errno == '1049') {
			$e = new Exception('failed to connect to mysql db: create the database (or fix the settings)', $this->db_errno);
		}
		if ($this->db_errno >= 2000) {
			$e = new Exception('failed to connect to mysql db: start mysql (or fix the settings)', $this->db_errno);
		}
		error::mail($e, false, $type='mysql');
		throw $e;
	}
	
	$this->db_connection = $connection;
	
	$this->db_query_raw("SET NAMES utf8;");
	$this->db_query_raw("SET CHARACTER SET utf8;");
}

}
