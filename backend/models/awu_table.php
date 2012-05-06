<?php
/*------------------------------------------------------------------------------
	base model for aw universe tables
------------------------------------------------------------------------------*/

class awu_table extends table {

// tables use 'ID' instead of 'id'
protected $index_key = 'ID';

// connect to another db
protected function db_config() {
	return new config('mysql', 'aw_universe');
}

/*------------------------------------------------------------------------------
	don't allow to to update the tables -- let aw manage
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
