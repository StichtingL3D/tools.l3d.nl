<?php
/*------------------------------------------------------------------------------
	sessions
------------------------------------------------------------------------------*/

class sessions_model extends models {

protected $table = 'sessions';
private $cookie_name = 'session';
private $type = false;

public function __construct($type=false) {
	parent::__construct();
	
	if ($type) {
		$this->cookie_name = APP_NAME.'_'.$type;
		$this->type = $type;
	}
	else {
		$this->cookie_name = APP_NAME.'_'.$this->cookie_name;
	}
}

/*------------------------------------------------------------------------------
	creating new sessions
------------------------------------------------------------------------------*/
public function create_new($user_level=false, $user_id=false) {
	// get a new session id
	$new_id = $this->generate_new_id($user_level);
	
	// create the session in the database
	$new_data = array(
		'id' => $new_id,
		'challenge_data' => $this->get_challenge_data(),
		'last_seen' => time(),
	);
	
	// connect to a user
	// always trusted until implementation of delayed-authentication (delay-auth)
	if ($user_id) {
		$new_data['user_id'] = $user_id;
	}
	
	parent::insert($new_data);
	
	// also create a new cookie
	$expire = false;
	cookie::set($this->cookie_name, $new_id, $expire);
	
	return $new_id;
}

private function generate_new_id($user_level=false) {
	$config = load::config('sessions');
	
	if ($user_level && !empty($config['secretkey_'.$user_level])) {
		$app = $config['secretkey_'.$user_level];
	}
	else {
		$app = $config['secretkey_everyone'];
	}
	
	$client = $_SERVER['REMOTE_ADDR'];
	$time = microtime();
	$random = mt_rand();
	
	$new_id = sha1($app.$client.$time.$random);
	return $new_id;
}

/*------------------------------------------------------------------------------
	deleting sessions
------------------------------------------------------------------------------*/
public function delete_current() {
	if (empty($_COOKIE[$this->cookie_name])) {
		// can't delete the session without a session id
		return false;
	}
	$id = $_COOKIE[$this->cookie_name];
	
	// remove from database
	$sql = "DELETE FROM `".$this->table."` WHERE `id` = '%s' LIMIT 1;";
	mysql::query($sql, $id);
	
	// also from the cookie
	cookie::remove($this->cookie_name);
}

/*------------------------------------------------------------------------------
	internal functions
------------------------------------------------------------------------------*/
private function get_challenge_data() {
	// add data to challenge the session with on a later point
	// copy-pasted to error-helper
	$challenge = array(
		'ip_address'      => (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '',
		'user_agent'      => (isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : '',
		
		'accept_content'  => (isset($_SERVER['HTTP_ACCEPT'])) ? $_SERVER['HTTP_ACCEPT'] : '',
		'accept_language' => (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '',
		'accept_encoding' => (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '',
		'accept_charset'  => (isset($_SERVER['HTTP_ACCEPT_CHARSET'])) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : '',
	);
	$challenge_json = json_encode($challenge);
	
	return $challenge_json;
}

}
?>