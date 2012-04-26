<?php
/*------------------------------------------------------------------------------
	user model -- redirect to citizen
------------------------------------------------------------------------------*/

class user {

private $citizen;

public function __construct($id) {
	$this->citizen = new citizen($id);
}

public function __get($key) {
	// translate keys
	if ($key == 'emailaddress') {
		$key = 'Email';
	}
	
	return $this->citizen->$key;
}

public function __call($func_name, $arguments) {
	return call_user_func_array(array($this->citizen, $func_name), $arguments);
}

}
