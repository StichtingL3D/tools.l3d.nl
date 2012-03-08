<?php
/*------------------------------------------------------------------------------
	model, used as a base for querying object's information
	
	start with load::model($name, $id) and use:
	- magic __get() and __set(): $a = property or property = $a
	- get_object()
	- get_property($key)
	- update_property($key, $new_value)
	- update_counter($key, $operator='+', $amount=1)
	- empty_property($key, $replacement_value='')
	
	changes are staged, and only written to the database on destruct
------------------------------------------------------------------------------*/

class model {

public $id = false;
protected $table = 'test';
protected $id_isint = true;
private $id_regex = "%d";

protected $table_data;
protected $table_changes;

public function __construct($id) {
	// check for a correct id
	if ($this->id_isint) {
		if (is_int($id) && $id > 0) {
			// fine
		}
		elseif (is_int($id) && $id <= 0) {
			throw new Exception('no valid id ('.$this->table.')');
		}
		elseif (is_int($id) == false) {
			if (preg_match('/^[0-9]+$/', $id) == false) {
				throw new Exception('no valid id ('.$this->table.')');
			}
			// glue: turn id's into real ints
			$id = (int)$id;
		}
	}
	
	// some ids are strings (like session-ids)
	else {
		if (empty($id)) {
			throw new Exception('empty id ('.$this->table.')');
		}
		$this->id_regex = "'%s'";
	}
	
	// load & cache initial data
	$this->id = $id;
	$this->get_object();
}

/*------------------------------------------------------------------------------
	selecting
------------------------------------------------------------------------------*/
public function get_property($key) {
	// test *key* existence, use array_key_exists() as db *value* could be NULL
	if (array_key_exists($key, $this->table_data) == false) {
		throw new Exception('key "'.$key.'" doesn\'t exist in model('.$this->table.')');
	}
	
	return $this->table_data[$key];
}

public function __get($key) {
	return $this->get_property($key);
}

public function __isset($key) {
	$value = $this->get_property($key);
	return isset($value);
}

public function get_object() {
	if (empty($this->table_data)) {
		$sql = "SELECT * FROM `".$this->table."` WHERE `id` = ".$this->id_regex.";";
		$this->table_data = mysql::select('row', $sql, $this->id);
		
		if ($this->table_data == false) {
			throw new Exception('empty result set ('.$this->table.')', 404);
		}
	}
	
	return $this->table_data;
}

/*------------------------------------------------------------------------------
	updating
------------------------------------------------------------------------------*/
public function update_property($key, $new_value) {
	// stage the new value for later writing
	$this->table_data[$key] = $new_value;
	$this->table_changes[$key] = $new_value;
}

public function __set($key, $new_value) {
	return $this->update_property($key, $new_value);
}

public function __unset($key) {
	throw new Exception('can not unset table fields', 500);
}

// doesn't use the write cache ($this->table_changes) as it doesn't fit in a simple way
public function update_counter($key, $operator='+', $amount=1) {
	$operator = ($operator == '+') ? '+' : '-';
	
	$sql = "UPDATE `".$this->table."` SET `%s` = `%s` ".$operator." %d WHERE `id` = ".$this->id_regex.";";
	mysql::query($sql, $key, $key, $amount, $this->id);
	
	// don't update the cache: it is too expensive for a unimportant counter
}

public function empty_property($key, $replacement_value='') {
	return $this->update_property($key, $replacement_value);
}

/*------------------------------------------------------------------------------
	closing the model
	purge the updated keys
------------------------------------------------------------------------------*/
public function __destruct() {
	try {
		// write changes to the database
		$this->update_object();
	}
	catch (Exception $e) {
		
		// not allowed to throw exceptions during destruct
		try {
			error::mail($e);
		}
		catch (Exception $e) {
			// i said NOT allowed, skip silently
		}
		
	}
}

protected function update_object() {
	if (is_array($this->table_changes) && count($this->table_changes)) {
		
		$sql = "UPDATE `".$this->table."` SET ";
		$mysql_args = array();
		
		// collect changes
		foreach ($this->table_changes as $key => $new_value) {
			$sql .= "`".$key."` = ";
			$sql .= (ctype_digit($new_value) && $new_value[0] !== '0') ? "%d," : "'%s',";
			$mysql_args[] = $new_value;
		}
		$sql = rtrim($sql, ",");
		
		// finish up
		$sql .= " WHERE `id` = ".$this->id_regex.";";
		$mysql_args[] = $this->id;
		
		// write all updates to the database
		array_unshift($mysql_args, $sql);
		call_user_func_array('mysql::query', $mysql_args);
		
	}
}

}
?>