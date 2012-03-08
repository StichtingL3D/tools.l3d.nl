<?php
/*------------------------------------------------------------------------------
	users
------------------------------------------------------------------------------*/

class users_model extends models {

protected $table = 'users';

/*------------------------------------------------------------------------------
	find users
------------------------------------------------------------------------------*/
public function find_user($emailaddress) {
	$sql = "SELECT `id`, `active`, `level` FROM `users` WHERE `emailaddress` = '%s';";
	$user_info = mysql::select('row', $sql, $emailaddress);
	if ($user_info == false) {
		return false;
	}
	
	$user_info['id'] = (int)$user_info['id'];
	
	return $user_info;
}

public function find_email($id) {
	$sql = "SELECT `emailaddress` FROM `users` WHERE `id` = %d;";
	$emailaddress = mysql::select('field', $sql, $id);
	
	return $emailaddress;
}

/*------------------------------------------------------------------------------
	check email & pass combination
------------------------------------------------------------------------------*/
public function check_and_login($emailaddress, $password) {
	// verify email/pass combination
	$accepted_user_id = $this->check_login($emailaddress, $password);
	
	// check failed?
	if ($accepted_user_id == false) {
		throw new Exception('invalid email address & password combination');
	}
	
	// check active-bit
	$user = load::model('user', $accepted_user_id);
	if ($user->get_property('active') < 1) {
		throw new Exception('user is inactive');
	}
	
	// delete old session if it exists
	$sessions = load::model('sessions');
	$sessions->delete_current();
	
	// log the user in (by connecting the user_id to the current session)
	$sessions->create_new($user->level, $accepted_user_id);
	
	return $accepted_user_id;
}

public function check_login($emailaddress, $password) {
	// get password salt, and check double emailadres directly
	$sql = "SELECT `password_salt` FROM `users` WHERE `emailaddress` = '%s';";
	$user_salt = mysql::select('field', $sql, $emailaddress);
	if (mysql::$num_rows > 1) {
		throw new Exception('duplicate emailaddress in users table', 500);
	}
	
	// create password hash
	load::helper('user');
	$password_hash = user::hash_password($password, $user_salt);
	
	// check password hash against db
	$sql = "SELECT `id` FROM `users` WHERE `emailaddress` = '%s' AND `password_hash` = '%s';";
	$check = mysql::select('field', $sql, $emailaddress, $password_hash);
	if (mysql::$num_rows > 1) {
		throw new Exception('duplicate emailaddress in users table', 500);
	}
	
	return $check;
}

/*------------------------------------------------------------------------------
	inserting
------------------------------------------------------------------------------*/
public function add($emailaddress, $level, $require_change=1) {
	if ($require_change !== 1) {
		$require_change = 0;
	}
	
	$new_data = array(
		'emailaddress' => $emailaddress,
		'level' => $level,
		'password_change_required' => $require_change,
	);
	return parent::insert($new_data);
}

}
?>