<?php
/*------------------------------------------------------------------------------
	citizen model
	
	TODO:
	- update changed field on changes?
------------------------------------------------------------------------------*/

class citizen extends awu_table {

public function __toString() {
	return $this->Name;
}

/*------------------------------------------------------------------------------
	user specific
------------------------------------------------------------------------------*/
public function goto_home($next=false) {
	$new_page = '';
	
	// catch the requested 'next' page
	if ($next) {
		$new_page = $next;
	}
	elseif ($next == false && !empty($_GET['next'])) {
		$new_page = input::url_argument($_GET['next']);
		$new_page = input::validate($new_page, array('url'=>'relative'), $silent=true);
	}
	
	// else the dashboard depends on user level
	else {
		#if ($this->is_webmaster()) {
		#	$new_page = 'beheer';
		#}
	}
	
	// redirect
	load::redirect($new_page);
	exit;
}

/*------------------------------------------------------------------------------
	level checks
------------------------------------------------------------------------------*/
public function is_enabled() {
	if ($this->Enabled == false) {
		return false;
	}
	if ($this->Expiration < time() && $this->Expiration != '0') {
		return false;
	}
	
	return true;
}

protected function is_level($check_level_name) {
	if ($this->level != $check_level_name) {
		return false;
	}
	
	if ($check_level_name == 'universect' || $check_level_name == 'webmaster') {
		// also check the admin bits
		if ($this->Beta != '1' || $this->Expiration != '0') {
			return false;
		}
	}
	
	return true;
}

protected function is_level_or_higher($check_level_name) {
	// is level
	if ($this->is_level($check_level_name)) {
		return true;
	}
	
	// or higher
	$level_names = array('citizen','honered','worldct','l3dmember','universect','webmaster');
	$level_ids = array_flip($level_names);
	$check_level_id = $level_ids[$check_level_name];
	
	foreach ($level_names as $level_id => $level_name) {
		// skip lower levels
		if ($level_id < $check_level_id) {
			continue;
		}
		
		if (call_user_func(array($this, 'is_level'), $level_name)) {
			return true;
		}
	}
	
	return false;
}

public function __call($function_name, $arguments) {
	// is_level_or_higher()
	if (strpos($function_name, 'is_') === 0 && strpos($function_name, '_or_higher')) {
		$level_name = str_replace(array('is_', '_or_higher'), '', $function_name);
		return call_user_func(array($this, 'is_level_or_higher'), $level_name);
	}
	
	// is_level()
	elseif (strpos($function_name, 'is_') === 0) {
		$level_name = str_replace('is_', '', $function_name);
		return call_user_func(array($this, 'is_level'), $level_name);
	}
	
	else {
		throw new Exception('method "'.$function_name.'" does not exists in class "'.get_class($this).'".');
	}
}

/*------------------------------------------------------------------------------
	login and password stuff
------------------------------------------------------------------------------*/
public function check_and_login($username, $password) {
	// verify email/pass combination
	$accepted_citizen_id = $this->check_login($username, $password);
	
	// check failed?
	if ($accepted_citizen_id == false) {
		throw new Exception('invalid login combination');
	}
	
	// check if enabledand not expired
	$citizen = new citizen($accepted_citizen_id);
	if ($citizen->Enabled < 1) {
		throw new Exception('user is inactive');
	}
	if ($citizen->Expiration < time() && $citizen->Expiration !== '0') {
		throw new Exception('user is expired');
	}
	
	// delete old session if it exists
	$sessions = load::model('sessions');
	$sessions->delete_current();
	
	// log the user in (by connecting the user_id to the current session)
	$sessions->create_new($citizen->level, $accepted_citizen_id);
	
	return $accepted_citizen_id;
}

private function check_login($username, $password) {
	$username_type = 'Name';
	$username_regex = "'%s'";
	
	// login is also allowed using citizen id or emailaddress
	if (is_int($username)) {
		$username_type = $this->index_key;
		$username_regex = "%d";
	}
	if (strpos($username, '@')) {
		$username_type = 'Email';
		// only allowed when it is used only once
		if ($this->count(false, array($username_type=>$username)) !== '1') {
			throw new Exception('can not login with multi-used emailaddress');
		}
	}
	
	$sql = "SELECT `".$this->index_key."` FROM `".$this->table."` WHERE `".$username_type."` = ".$username_regex." AND `Password` = '%s';";
	$check = $this->db_select('field', $sql, $username, $password);
	if ($this->db_num_rows > 1) {
		throw new Exception('duplicate login conbination in citizens table', 500);
	}
	
	return $check;
}

public function set_new_password($new_password, $require_change_on_login=false) {
	// changes by admins require a change on login by the user
	$password_change_required = 0;
	if ($require_change_on_login) {
		$password_change_required = 1;
	}
	
	// update the database directly
	$sql = "UPDATE `".$this->table."` SET `Password` = '%s' WHERE `".$this->index_key."` = %d LIMIT 1;";
	$result = mysql::query($sql, $new_pass_hash, $new_pass_salt, $password_change_required, $this->id);
	
	// update the database directly
	$this->details->password_change_required = $password_change_required;
	
	return $result;
}

/*------------------------------------------------------------------------------
	override extended model
------------------------------------------------------------------------------*/
protected $table = 'awu_citizen';
private $details;

public function __construct($id=null) {
	parent::__construct($id);
	
	$this->details = new citizen_details($this->id);
}

public function get_property($key) {
	if ($key == 'Password' || $key == 'PrivPass') {
		throw new Exception('can not get "'.$key.'" directly');
	}
	elseif ($key == 'level') {
		return $this->details->level;
	}
	elseif ($key == 'password_change_required') {
		return $this->details->password_change_required;
	}
	
	return parent::get_property($key);
}

public function get_object() {
	parent::get_object();
	unset($this->table_data['Password']);
	unset($this->table_data['PrivPass']);
	
	return $this->table_data;
}

public function update_property($key, $new_value) {
	if ($key == 'LastLogin' || $key == 'LastAddress' || $key == 'TotalTime') {
		throw new Exception('can not update "'.$key.'", updates are done automaticly');
	}
	elseif ($key == 'Privacy' || $key == 'Trial') {
		throw new Exception('can not update "'.$key.'", not used');
	}
	elseif ($key == 'Immigration') {
		throw new Exception('can not update "'.$key.'", one-time field');
	}
	elseif ($key == 'Password' || $key == 'PrivPass' || $key == 'Expiration') {
		throw new Exception('can not update "'.$key.'" directly, use special method');
	}
	
	return parent::update_property($key, $new_value);
}

}
