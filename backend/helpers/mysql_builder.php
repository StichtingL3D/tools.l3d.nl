<?php
/*------------------------------------------------------------------------------
	mysql builder - helping out to build statements
------------------------------------------------------------------------------*/

class mysql_builder {

/*------------------------------------------------------------------------------
	keys
------------------------------------------------------------------------------*/
public static function keys($keys) {
	$key_str = str_repeat("`%s`,", count($keys));
	$key_str = rtrim($key_str, ",");
	
	return $key_str;
}

/*------------------------------------------------------------------------------
	where
------------------------------------------------------------------------------*/
public static function where($where, &$args) {
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
			$where_str .= self::where_operator($statement[0]);
			if ($statement[0] != '(' && $statement[0] != ')') {
				$operator_count++;
			}
		}
		else {
			$where_str .= self::where_statement($statement, $args);
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

private static function where_statement($statement, &$args) {
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

private static function where_operator($operator) {
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

/*------------------------------------------------------------------------------
	misc
------------------------------------------------------------------------------*/
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

}
?>