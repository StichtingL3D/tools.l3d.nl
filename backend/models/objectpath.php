<?php
/*------------------------------------------------------------------------------
	objectpath model
------------------------------------------------------------------------------*/

class objectpath extends table {

public function __toString() {
	return $this->domain.'props.l3d.nl';
}

/*------------------------------------------------------------------------------
	override extended model
------------------------------------------------------------------------------*/
public function __construct($id=null) {
	if (is_numeric($id) == false) {
		$this->index_key = 'domain';
		$this->id_isint = false;
	}
	
	parent::__construct($id);
}

public function get_property($key) {
	if ($key == 'password') {
		throw new Exception('can not get "'.$key.'" directly');
	}
	
	return parent::get_property($key);
}

public function get_object() {
	parent::get_object();
	unset($this->table_data['password']);
	
	return $this->table_data;
}

}
