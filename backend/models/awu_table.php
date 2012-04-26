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

}
