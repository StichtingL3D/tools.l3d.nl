<?php
/*------------------------------------------------------------------------------
	models, used as a base for querying / filtering over multiple objects
	
	start with load::model($name) and use:
	- get_all($keys=false, $where=false, $group=false, $order=false, $limit=100)
	- insert($new_data, $replace=false)
	
	TODO:
	- caching (complete sql queries to json files)
------------------------------------------------------------------------------*/

class models {

public $error = false;
protected $table = 'test';

public function __construct() {
	$this->test();
}

public function test() {
	$result = mysql::query("SHOW TABLES LIKE '".$this->table."';");
	if ($result->fetch_assoc() == false) {
		throw new Exception('table doesn\'t exist');
	}
}

/*------------------------------------------------------------------------------
	selecting
	- $keys		string or array with asked key(s)
							"title" or array("title", "text")
	- $where	an array containing where statements
							array("color", "blue") or array(array("color", "blue"), array("id", "<", "5"))
	- $group	a string with the key name to group by
	- $order	a string with the key name to order by (defaults to ASC, prepend with - for DESC)
	- $limit	an integer limiting the amount of results in the set, defaults to 100
------------------------------------------------------------------------------*/
public function get_all($keys=false, $where=false, $group=false, $order=false, $limit=100) {
	$sql_args = array();
	
	// add `%s` for all keys and add the keys to the args array
	$key_str = "*";
	if ($keys != false && $keys != "*") {
		if (!is_array($keys)) {
			$keys = array($keys);
		}
		$key_str = self::build_keys($keys);
		$sql_args += $keys;
	}
	
	// simple filter using where statements
	$where_str = "";
	if ($where && is_array($where)) {
		$where_str = "WHERE ".self::build_where($where, $sql_args);
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
		$all = call_user_func_array('mysql::select', $sql_args);
	}
	catch (Exception $e) {
		$this->error = mysql::$error;
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
		$sql .= " WHERE ".self::build_where($where, $sql_args);
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
		$result = call_user_func_array('mysql::select', $sql_args);
		
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
		$result = call_user_func_array('mysql::query', $sql_args);
		$count = $result->fetch_array();
		$count = $count[0];
	}
	
	return $count;
}

/*------------------------------------------------------------------------------
	inserting
	- $new_data: array with key-value pairs used in the SET-statement
			array(key = value, second_key = other_value, ..)
	returns insert-id
------------------------------------------------------------------------------*/
public function insert($new_data, $replace=false) {
	$sql = ($replace) ? "REPLACE" : "INSERT";
	$sql .= " INTO `".$this->table."` SET ";
	$sql_args = array();
	
	foreach ($new_data as $key => $value) {
		$sql .= "`%s` = ";
		$sql .= (is_int($value)) ? "%d" : "'%s'";
		$sql .= ",";
		
		$sql_args[] = $key;
		$sql_args[] = $value;
	}
	$sql = rtrim($sql, ",");
	$sql .= ";";
	
	array_unshift($sql_args, $sql);
	call_user_func_array('mysql::query', $sql_args);
	
	return mysql::$insert_id;
}

/*------------------------------------------------------------------------------
	sql helpers
------------------------------------------------------------------------------*/

/*
get_by_relation(
	$select = 'location',
	$from   = 'photographers_locations',
	$where  = 'photographer',
	$is     = '10'
)
*/
public function get_by_relation($select, $table, $where, $value) {
	$relation_sql = "SELECT `%s_id` AS 'id' FROM `rel_%s` WHERE `%s_id` = ";
	if (is_int($value)) {
		$relation_sql .= "%d";
	}
	if ($value == 'NULL') {
		$relation_sql .= "'%s'";
	}
	$relation_sql .= ";";
	
	$relation_ids = mysql::select('array', $relation_sql, $select, $table, $where, $is);
	$relation_where = $this->ids_to_where_statement($relation_ids);
	
	return $relation_where;
}

public function ids_to_where_statement($rows, $prefix=false) {
	$where = array();
	foreach ($rows as $row) {
		$where[] = array($prefix.'id' => $row['id']);
		$where[] = 'or';
	}
	
	// remove the last 'or'
	array_pop($where);
	
	return $where;
}

/*------------------------------------------------------------------------------
	sql builders
------------------------------------------------------------------------------*/
protected static function build_keys($keys) {
	$key_str = str_repeat("`%s`,", count($keys));
	$key_str = rtrim($key_str, ",");
	
	return $key_str;
}

protected static function build_where($where, &$args) {
	// wrap single statement-wheres, to make the foreach down the line happy
	$first_key = key($where);
	if (!is_array($where[$first_key])) {
		$where = array($where);
	}
	
	$where_str = "";
	$statement_count = 0;
	$operator_count = 0;
	foreach ($where as $statement) {
		// re-format lonely operators: and, instead of array(and)
		if (is_string($statement)) {
			$statement = array($statement);
		}
		
		// re-format assoc arrays to numeric arrays
		// making it possible to use array(key => value) instead of array(key, value)
		$statement_key = key($statement);
		if (is_string($statement_key)) {
			$statement = array($statement_key, current($statement));
		}
		
		// execute
		if (count($statement) === 1) {
			$where_str .= self::build_where_operator($statement[0]);
			if ($statement[0] != '(' && $statement[0] != ')') {
				$operator_count++;
			}
		}
		else {
			$where_str .= self::build_where_statement($statement, $args);
			$statement_count++;
		}
	}
	
	// secure where statements from missing operators
	// multiple statements should have an operator in between each (operator = statement -1)
	if ($statement_count > 1 && $operator_count != $statement_count-1) {
		throw new Exception('missing operator in mysql where statement', 500);
	}
	
	return $where_str;
}

private static function build_where_statement($statement, &$args) {
	// get the parts
	if (count($statement) == 2) {
		$operator = "=";
		$second = $statement[1];
	}
	elseif (count($statement) == 3) {
		$operator = $statement[1];
		$second = $statement[2];
	}
	else {
		return false;
	}
	$first = $statement[0];
	
	// first
	$first_str = "`%s`";
	$args[] = $first;
	
	// operator
	$operator_str = "=";
	$allowed_operators = array_flip(array('=', '!=', '<>', '<', '>', '<=', '>=', 'IS', 'IS NOT'));
	if (isset($allowed_operators[$operator])) {
		if ($operator == 'IS' || $operator == 'IS NOT') {
			$operator = ' '.$operator.' ';
		}
		$operator_str = $operator;
	}
	
	// second: string or decimal
	$second_str = "'%s'";
	if (is_int($second)) {
		$second_str = "%d";
	}
	if ($second == 'NULL') {
		$second_str = "%s";
	}
	$args[] = $second;
	
	return $first_str.$operator_str.$second_str;
}

private static function build_where_operator($operator) {
	$operator = strtoupper($operator);
	$allowed_operators = array(
		'('   => ' ( ',
		')'   => ' ) ',
		'OR'  => ' OR ',
		'||'  => ' OR ',
		'NOT' => ' NOT ',
		'!'   => ' NOT ',
		'XOR' => ' XOR ',
		'AND' => ' AND ',
		'&&'  => ' AND ',
	);
	
	if (isset($allowed_operators[$operator])) {
		return $allowed_operators[$operator];
	}
	else {
		return ' AND ';
	}
}

}
?>