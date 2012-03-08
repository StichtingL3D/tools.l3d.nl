<?php
/*------------------------------------------------------------------------------
	forms
------------------------------------------------------------------------------*/

class FormException extends Exception {}

class forms {

/*------------------------------------------------------------------------------
	get a new token, returns html with a hidden token field
	
	$controller
		the technical name of the controller, i.e. user/login
		you can add elements which make it more unique, like an id for the page
------------------------------------------------------------------------------*/

public static function get_token($controller, $ajax=true, $html=true) {
	$token = self::generate_new_token();
	
	$forms = load::model('forms');
	$form_token = $forms->add($token, $controller, $ajax);
	
	if ($html) {
		$token_html = self::get_token_html($form_token);
		return $token_html;
	}
	else {
		return $token;
	}
}

private static function generate_new_token() {
	$config = load::config('forms');
	
	$app = $config['token_salt'];
	$client = $_SERVER['REMOTE_ADDR'];
	$time = microtime();
	$random = mt_rand();
	
	$new_token = sha1($app.$client.$time.$random);
	return $new_token;
}

private static function get_token_html($token) {
	return mustache_tpl::parse('forms/form_token', array('token'=>$token));
}

/*------------------------------------------------------------------------------
	checking the token
	
	$controller: the same as used to get the token
	
	returns true
		everything is fine, validation still needs to be done
	
	throws FormException
		for lighter/possible hacking attempts
		* too old: requested the form too long ago (currently 12 hours)
		fallback to a normal exception and tell 'something went wrong'
		or tell the user to re-submit, and that their information might just be fine
	
	doesn't return
		the request is believed to be a serious hacking attempt
		and the user will get an error page directly
------------------------------------------------------------------------------*/

public static function check($controller, $input_validation=false) {
	try {
		self::check_referer(); // can throw 'wrong referer'
		
		// get the token
		# temporary only email the webmaster on form-token hacks
		#TODO: test where errors come from, and re-enable it then
		if (empty($_POST['token'])) {
			# temporary only mail webmaster
			error::mail($e='no token (revert POSTed data!)', $message='hack');
			#throw new Exception('no token'); // hack
		}
		else {
			$token = input::validate($_POST['token'], 'hash', $silent=true);
			unset($_POST['token']);
			
			// check if token is known, in combination with this controller
			$forms = load::model('forms');
			if ($forms->check($token, $controller) == false) {
				# temporary only mail webmaster
				error::mail($e='token not found (revert POSTed data!)', $message='hack');
				#throw new Exception('token not found'); // hack
			}
			
			// check token's age and session
			$form = load::model('form', $token);
			$form->check(); // can throw: too old, wrong session, no session
		}
		
		// validate the input
		if ($input_validation) {
			return input::validate($_POST, $input_validation);
		}
		return true;
	}
	catch (InputException $e) { // probably never happens as we don't call secure()
		throw $e;
	}
	catch (ValidationException $e) {
		self::notify_on_error($controller, $input_validation, $e->errors);
		throw $e;
	}
	catch (Exception $e) {
		if (isset($form)) {
			$form->update_property('status', $e->getMessage());
		}
		
		// let the controller handle these exceptions
		if ($e->getMessage() == 'too old') {
			throw new FormException('re-submit', 0, $e);
		}
			
		// other exceptions are bad, really really bad
		error::hack($e);
		exit;
	}
}

// check for a wrong referer (defense in depth, easy to circumvent)
private static function check_referer() {
	if (!empty($_SERVER['HTTP_REFERER'])) {
		$places = json_decode(PLACES, true);
		if (strpos($_SERVER['HTTP_REFERER'], $places['www']) !== 0) {
			unset($_POST);
			throw new Exception('wrong referer');
		}
	}
}

// notify on form errors
private static function notify_on_error($controller, $validation_rules, $errors) {
	// strip forms with passwords
	if (strpos($controller, 'login') || strpos($controller, 'password')) {
		if (isset($_POST['password']))  $_POST['password']  = '(wachtwoord niet getoond)';
		if (isset($_POST['new']))       $_POST['new']       = '(wachtwoord niet getoond)';
		if (isset($_POST['current']))   $_POST['current']   = '(wachtwoord niet getoond)';
	}
	
	try {
		try {
			$user = load::model('user', $user_id='session');
			$user_info = array('id'=>$user->id, 'emailaddress'=>$user->emailaddress);
			
			$photographers = load::model('photographers');
			$pg_info = $photographers->find_by_userid($user->id, $extended=true);
		}
		catch (Exception $e) {
			$user_info = false;
			$pg_info = false;
		}
		
		$cron_args = array(
			'form'		=> $controller,
			'time'		=> time(),
			'post'		=> $_POST,
			'errors'	=> $errors,
			'rules'		=> $validation_rules,
			'user'		=> $user_info,
			'pg'			=> $pg_info,
		);
		cron::execute('forms::notify_on_error', $cron_args);
	}
	catch (Exception $e) {
		// silently skip
	}
}

}
?>