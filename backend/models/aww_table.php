<?php
/*------------------------------------------------------------------------------
	base model for aw worldserver tables
------------------------------------------------------------------------------*/

class aww_table extends table {

// tables use 'ID' instead of 'id'
protected $index_key = 'ID';

// connect to another db
protected function db_config() {
	return new config('mysql', 'aw_worldserver');
}

/*------------------------------------------------------------------------------
	allow multi-database queriing
------------------------------------------------------------------------------*/
/*
protected $db_names;

public function __construct($id=null) {
	parent::__construct($id);
	
	$this->db_names = array(
		'aw_wl3d',
		'aw_wschool1',
	);
}

public function get_object() {
	try {
		foreach ($this->db_names as $db_name) {
			$this->db_switch($db_name);
			parent::get_object();
		}
	}
	catch (Exception $e) {
		
	}
}
public function select($keys=false, $where=false, $group=false, $order=false, $limit=100) {
	foreach ($this->db_names as $db_name) {
		$this->db_switch($db_name);
		parent::select($keys, $where, $group, $order, $limit);
	}
}
public function count($key=false, $where=false) {
	foreach ($this->db_names as $db_name) {
		$this->db_switch($db_name);
		parent::count($key, $where);
	}
}
*/

/*------------------------------------------------------------------------------
	don't allow to to update the tables -- let aw manage
	also, it is quite difficult to manage with multiple databases
------------------------------------------------------------------------------*/
public function update_property($key, $new_value) {
	throw new Exception('can not modify aw worldserver tables / databases');
}
public function update_counter($key, $operator='+', $amount=1) {
	throw new Exception('can not modify aw worldserver tables / databases');
}
protected function insert($new_data, $replace=false) {
	throw new Exception('can not modify aw worldserver tables / databases');
}
protected function update_object() {
	return;
}

}
