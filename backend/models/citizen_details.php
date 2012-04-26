<?php
/*------------------------------------------------------------------------------
	citizen details
------------------------------------------------------------------------------*/

class citizen_details extends table {

protected $index_key = 'citizen_id';

public function get_object() {
	// rows in this table are optional and have default field values
	try {
		parent::get_object();
	}
	catch (Exception $e) {
		if (strpos($e->getMessage(), 'empty result set') === 0) {
			$this->table_data['level'] = 'citizen';
			$this->table_data['password_change_required'] = false;
		}
		else {
			throw $e;
		}
	}
	
	return $this->table_data;
}

}
