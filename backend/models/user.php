<?php
/*------------------------------------------------------------------------------
	user
------------------------------------------------------------------------------*/

class user_model extends model {

protected $table = 'users';

/*------------------------------------------------------------------------------
	start the model with the session's user-id
------------------------------------------------------------------------------*/
public function __construct($id) {
	if ($id == 'session') {
		try {
			$session = load::model('session');
			$id = $session->user_id;
		}
		catch (Exception $e) {
			$id = false; // will throw an exception in parent model
		}
	}
	
	return parent::__construct($id);
}

/*------------------------------------------------------------------------------
	user specific
------------------------------------------------------------------------------*/
public function goto_home($next=false) {
	// catch the requested 'next' page
	if ($next == false && !empty($_GET['next'])) {
		$next = input::url_argument($_GET['next']);
		$next = input::validate($next, array('url'=>'relative'), $silent=true);
	}
	
	// password changes take prefference
	$password_change_required = $this->get_property('password_change_required');
	if ($password_change_required) {
		$new_page = 'wachtwoord/veranderen';
		
		// delay the requested-'next'
		if ($next) {
			$new_page .= '?next='.output::url_argument($next);
		}
	}
	
	// requested 'next' page after login
	elseif ($next) {
		$new_page = $next;
	}
	
	// else the dashboard depends on user level
	else {
		/*
		if ($this->is_citizen()) {
			$new_page = 'dashboard';
		}
		if ($this->is_worldct()) {
			$new_page = 'dashboard';
		}
		if ($this->is_l3dmember()) {
			$new_page = 'dashboard';
		}
		if ($this->is_universect()) {
			$new_page = 'dashboard';
		}
		if ($this->is_webmaster()) {
			$new_page = 'beheer';
		}
		*/
	}
	
	// redirect
	load::redirect($new_page);
	exit;
}

public function set_new_password($new_password, $require_change_on_login=false) {
	// create a new salt as well
	load::helper('user');
	$new_pass_salt = user::create_password_salt();
	$new_pass_hash = user::hash_password($new_password, $new_pass_salt);
	
	// changes by admins require a change on login by the user
	$password_change_required = 0;
	if ($require_change_on_login) {
		$password_change_required = 1;
	}
	
	// update the database directly
	$sql = "UPDATE `".$this->table."` SET
		`password_hash` = '%s',
		`password_salt` = '%s',
		`password_change_required` = %d
		WHERE `id` = %d
		LIMIT 1;";
	$result = mysql::query($sql, $new_pass_hash, $new_pass_salt, $password_change_required, $this->id);
	
	return $result;
}

/*------------------------------------------------------------------------------
	level checks
------------------------------------------------------------------------------*/
public function is_active() {
	$active_bit = $this->get_property('active');
	return $active_bit;
}

public function is_citizen() {
	return ($this->level == 'citizen') ? true : false;
}

public function is_worldct() {
	return ($this->level == 'worldct') ? true : false;
}

public function is_l3dmember() {
	return ($this->level == 'l3dmember') ? true : false;
}

public function is_universect() {
	if ($this->level != 'universect') {
		return false;
	}
	
	// also check the admin bit
	$admin_bit = (int)$this->get_property('admin');
	return ($admin_bit === 1) ? true : false;
}

public function is_webmaster() {
	if ($this->level != 'webmaster') {
		return false;
	}
	
	// also check the admin bit
	$admin_bit = (int)$this->get_property('admin');
	return ($admin_bit === 1) ? true : false;
}

/*------------------------------------------------------------------------------
	level check extra
------------------------------------------------------------------------------*/
public function is_worldct_or_higher() {
	if ($this->is_worldct() || $this->is_l3dmember_or_higher()) {
		return true;
	}
	return false;
}

public function is_l3dmember_or_higher() {
	if ($this->is_l3dmember() || $this->is_universect_or_higher()) {
		return true;
	}
	return false;
}

public function is_universect_or_higher() {
	if ($this->is_universect() || $this->is_webmaster()) {
		return true;
	}
	return false;
}

/*------------------------------------------------------------------------------
	overriding specific methods
------------------------------------------------------------------------------*/
public function get_property($key) {
	// block getting the password hash in an easy way
	if ($key == 'password_hash' || $key == 'password_salt') {
		throw new Exception('can\'t get the password hash from a user');
	}

	// try to get a full name from a citizens model
	if ($key == 'full_name') {
		/*
		if ($this->is_photographer_or_higher()) {
			try {
				$photographers = load::model('photographers');
				$photographer_id = $photographers->find_by_userid($this->id);
				$photographer = load::model('photographer', $photographer_id);
				return $photographer->full_name;
			}
			catch (Exception $e) {
				return false;
			}
		}
		*/
	}
	
	return parent::get_property($key);
}

public function get_object() {
	parent::get_object();
	unset($this->table_data['password_hash']);
	unset($this->table_data['password_salt']);
	
	return $this->table_data;
}

public function update_property($key, $new_value) {
	// block updating the password or the admin bit in an easy way
	if ($key == 'password_hash' || $key == 'password_salt') {
		throw new Exception('can\'t update the password hash or salt of a user');
	}
	if ($key == 'admin') {
		throw new Exception('can\'t update the admin bit of a user');
	}
	// block updating property from other models
	if ($key == 'full_name') {
		throw new Exception('use the photographer model to update a full name');
	}
	
	return parent::update_property($key, $new_value);
}

}
?>