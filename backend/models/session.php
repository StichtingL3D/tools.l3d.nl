<?php
/*------------------------------------------------------------------------------
	session
	
	TODO:
	- prevent cookie being set twice when db session is gone but cookie isn't
			delete the cookie at the constructor try-catch instead of only $id=false
	- delay-auth keyword for __construct($else) argument:
			create new session with untrusted connection to user id from cookie
			user should authenticate with password later on, when $else == 'login'
			users should have a previous - trusted - session connected to the user
			triggers when session only exists in the cookie or when it is too old
	- only update session cookie when previous session cookie is older then x min.
	- differentiate cookie expiration date between zero (when browser closes) for
			booking visitors, and a-long-time for loggedin users
			needs changes in create_new() and open() and expiration date management
------------------------------------------------------------------------------*/

class session_model extends model {

public $user_id;

protected $table = 'sessions';
protected $id_isint = false;
protected $returning = false;

private $cookie_name = 'session';
private $type = false;

/*------------------------------------------------------------------------------
	getting started with a session, opens the session with the cookie id
	
	throw an exception if session creation fails, or take $else_action:
	- login		redirect the user to the login (or re-auth page)
	- hide		redirect to 404 error, but don't email the webmaster
	defaults to throw an exception so the controller can use its own logic
------------------------------------------------------------------------------*/
public function __construct($else_action=false, $id=false, $type=false) {
	// startup
	if ($type) {
		$this->cookie_name = APP_NAME.'_'.$type;
		$this->type = $type;
	}
	else {
		$this->cookie_name = APP_NAME.'_'.$this->cookie_name;
	}
	$returning = false;
	
	// 1. check the cookie
	if ($id == false) {
		$id = $this->get_current_id();
	}
	
	// 2. check the database
	if ($id) {
		$returning = true;
		try {
			parent::__construct($id);
		}
		catch (Exception $e) {
			$id = false;
		}
	}
	
	// 3. login failed
	if ($id == false) {
		// throw an exception or take another action
		$this->take_action($else_action);
		exit;
	}
	
	//*--- session is created - from here on, $this->id exists ---*/
	
	// also create user-id
	$this->user_id = $this->get_property('user_id');
	
	// 4. update the session
	if ($returning) {
		$this->update();
	}
	
	// 5. open the session
	$this->get_data();
	
	// 6. optionally, force password change
	if ($else_action == 'login') {
		$this->check_password_change();
	}
}

/*------------------------------------------------------------------------------
	user requirements for this session
------------------------------------------------------------------------------*/
public function require_level($user_level, $else=false) {
	try {
		$this->check_user_requirements($user_level);
	}
	catch (Exception $e) {
		$this->take_action($else);
	}
}

public function require_user($user_level, $user_id, $else=false) {
	try {
		$this->check_user_requirements($user_level, $user_id);
	}
	catch (Exception $e) {
		$this->take_action($else);
	}
}

/*------------------------------------------------------------------------------
	get or set individual data keys
------------------------------------------------------------------------------*/
public function get($key) {
	$data = $this->get_data();
	
	$value = (isset($data[$key])) ? $data[$key] : '';
	// do not json decode this, this is already done in get_data()
	
	return $value;
}

public function set($key, $new_value) {
	$data = $this->get_data();
	
	// do not json encode this, this will be done in set_data()
	$data[$key] = $new_value;
	
	$this->set_data($data);
}

public function remove($key) {
	$data = $this->get_data();
	
	if (isset($data[$key])) {
		unset($data[$key]);
	}
	
	$this->set_data($data);
}

// get or set all data
public function get_data() {
	$data_json = $this->get_property('data');
	$data = json_decode($data_json, true);
	
	return $data;
}

public function set_data($new_data) {
	$new_data_json = json_encode($new_data);
	$this->update_property('data', $new_data_json);
}

// block magic __get & __set methods, as they are confused with get() & set()
public function __get($key) {
	throw new Exception('disabled magic __get() for session model, use ->get()');
}
public function __set($key, $new_value) {
	throw new Exception('disabled magic __set() for session model, use ->set()');
}

/*------------------------------------------------------------------------------
	easy accessible checks
------------------------------------------------------------------------------*/
private function is_x($level) {
	if ($this->user_id == false) {
		return false;
	}
	
	try {
		$user = load::model('user', $this->user_id);
		return call_user_func(array($user, 'is_'.$level));
	}
	catch (Exception $e) {
		return false;
	}
}

public function is_citizen() {
	return $this->is_x('citizen');
}
public function is_not_citizen() {
	return ($this->is_citizen()) ? false : true;
}

public function is_worldct() {
	return $this->is_x('worldct');
}
public function is_not_worldct() {
	return ($this->is_worldct()) ? false : true;
}

public function is_l3dmember() {
	return $this->is_x('l3dmember');
}
public function is_not_l3dmember() {
	return ($this->is_l3dmember()) ? false : true;
}

public function is_universect() {
	return $this->is_x('universect');
}
public function is_not_universect() {
	return ($this->is_universect()) ? false : true;
}

public function is_webmaster() {
	return $this->is_x('webmaster');
}
public function is_not_webmaster() {
	return ($this->is_webmaster()) ? false : true;
}

public function is_citizen_or_higher() {
	return ($this->is_citizen() || $this->is_worldct_or_higher());
}
public function is_worldct_or_higher() {
	return ($this->is_worldct() || $this->is_l3dmember_or_higher());
}
public function is_l3dmember_or_higher() {
	return ($this->is_l3dmember() || $this->is_universect_or_higher());
}
public function is_universect_or_higher() {
	return ($this->is_universect() || $this->is_webmaster());
}

/*------------------------------------------------------------------------------
	internal functions to starting up a session
------------------------------------------------------------------------------*/
protected function get_current_id() {
	if (!empty($this->id)) {
		return $this->id;
	}
	elseif (!empty($_COOKIE[$this->cookie_name])) {
		return $_COOKIE[$this->cookie_name];
	}
	else {
		return false;
	}
}

protected function check_user_requirements($require_level, $require_id=false) {
	$session_user_id = $this->get_property('user_id');
	
	// check if user is logged in
	if ($session_user_id == false) {
		throw new Exception('not loggedin');
	}
	if ($require_level == 'user') {
		// we're surely loggedin as user
		return;
	}
	
	// check the level
	$user = load::model('user', $session_user_id);
	if (strpos($require_level, '+')) {
		$require_level_function = 'is_'.str_replace('+', '_or_higher', $require_level);
		if (call_user_func(array($user, $require_level_function)) == false) {
			throw new Exception('wrong user-level');
		}
	}
	elseif ($user->level != $require_level) {
		throw new Exception('wrong user-level');
	}
	
	// check user id
	if ($require_id) {
		if ($user->id != $require_id) {
			throw new Exception('wrong user-id');
		}
	}
}

protected function take_action($action) {
	if ($action == 'login') {
		$current_page = $_SERVER['REQUEST_URI'];
		$current_page = substr($current_page, 1); // strip off the starting-slash
		load::redirect('inloggen?next='.$current_page);
		exit;
	}
	
	elseif ($action == 'hide') {
		load::error_404($e=false, $skip_mail=true);
	}
	
	elseif ($action == 'future') {
		load::error_future($e=false, $skip_mail=true);
	}
	
	else { // refuse is the default
		// can't find cookie session or open session from db
		throw new Exception('refuse');
	}
}

protected function update() {
	// update last seen
	$this->update_property('last_seen', time());
	
	// set a new cookie
	$expire = ($this->type == 'booking') ? 'week' : false;
	cookie::set($this->cookie_name, $this->id, $expire);
}

protected function check_password_change() {
	// check if a password change is required
	if ($this->user_id && REQUEST != 'wachtwoord/veranderen') {
		$user = load::model('user', $this->user_id);
		if ($user->password_change_required) {
			load::redirect('wachtwoord/veranderen');
			exit;
		}
	}
}

}
?>