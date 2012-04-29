<?php
/*------------------------------------------------------------------------------
	object model
------------------------------------------------------------------------------*/

class object extends table {

public function __toString() {
	return $this->filename.' ('.$this->type.')';
}

/*------------------------------------------------------------------------------
	override extended model
------------------------------------------------------------------------------*/
public function get_property($key) {
	if (strpos($key, 'type_') === 0) {
		return 'type_'.$this->type;
	}
	
	return parent::get_property($key);
}

}
