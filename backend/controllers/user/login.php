<?php
$error = '';
if (!empty($_POST)) {
	try {
		// validate the form
		$validations = array(
			'username' => array('required'),
			'password' => array('required'),
		);
		$data = forms::check('user/login', $validations);
		
		$citizens = new citizen();
		$accepted_user_id = $citizens->check_and_login($data['username'], $data['password']);
		
		// remove a possible set failed-login cookie
		if (isset($_COOKIE['failed_login_emailaddress'])) {
			load::helper('cookie');
			cookie::remove('failed_login_emailaddress');
		}
		
		// redirect specific users to their dashboard
		$citizen = new citizen($accepted_user_id);
		$citizen->goto_home();
		exit;
	}
	catch (ValidationException $e) {
		if ($e->ifexeq('emailaddress', 'emailaddress')) {
			load::redirect('inloggen?msg=email');
			exit;
		}
		else {
			load::redirect('inloggen?msg=wrong');
			exit;
		}
	}
	catch (Exception $e) {
		// save the emailaddress for password reset
		if (!empty($_POST['emailaddress'])) {
			load::helper('input');
			$emailaddress = input::secure($_POST['emailaddress'], $silent=true);
			
			load::helper('cookie');
			cookie::set('failed_login_emailaddress', $emailaddress);
		}
		
		// show this login form again
		load::redirect('inloggen?msg=wrong');
		exit;
	}
}

function login_form() {
	$message = false;
	if (isset($_GET['msg'])) {
		$messages = array(
			'email' => 'Er zit een foutje in het e-mailadres, kijk het even na.',
			'wrong' => 'Dat is niet juist.',
		);
		$message = (isset($messages[$_GET['msg']])) ? $messages[$_GET['msg']] : $messages['wrong'];
	}
	
	// catch the requested 'next' page
	$next = '';
	if (!empty($_GET['next'])) {
		$next = input::url_argument($_GET['next']);
		$next = input::validate($next, array('url'=>'relative'), $silent=true);
		
		load::helper('output');
		$next = output::url_argument($next);
	}
	
	$data = array('message'=>$message, 'next'=>$next, 'token'=>forms::get_token('user/login'));
	page::show('user/login', $data);
}
login_form();
exit;
?>