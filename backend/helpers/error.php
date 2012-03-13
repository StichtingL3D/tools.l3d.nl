<?php
/*------------------------------------------------------------------------------
	error - error logging and mailing
------------------------------------------------------------------------------*/

class error {

/*------------------------------------------------------------------------------
	email the webmaster
------------------------------------------------------------------------------*/
public static function mail($exception=false, $user_message=false, $type=false) {
	// try to get a location where the user was
	$user_location = false;
	if (defined('REQUEST') && REQUEST) {
		$user_location = '/'.REQUEST;
	}
	elseif (defined('CONTROLLER') && CONTROLLER) {
		$user_location = CONTROLLER;
	}
	elseif (isset($_SERVER['PHP_SELF']) && strpos($_SERVER['PHP_SELF'], 'helpers/cron.php')) {
		$user_location = 'CRON (helpers/cron.php)';
	}
	else {
		$user_location = '-onbekend-';
	}
	
	// subject
	$subject = 'Fout in '.$user_location;
	if ($type == 404) {
		$subject = 'Pagina '.$user_location.' niet gevonden';
	}
	
	// body
	$body = 'Er ging iets mis.'.NL;
	if ($user_message) {
		$body .= 'De gebruiker kreeg deze melding: "'.strip_tags($user_message).'".'.NL;
	}
	$body .= self::add_server_info($exception, $type);
	
	// send (silently)
	cron::mail(WEBMASTER, $subject, $body);
}

/*------------------------------------------------------------------------------
	hacking attempts
	
	currently shows the user a standard error-500, to mask what is really going on
------------------------------------------------------------------------------*/
public static function hack($exception=false, $skip_mail=false) {
	// notify the webmaster
	if ($skip_mail == false && ENVIRONMENT != 'development') {
		$body = 'Iemand probeert ons te flessen..'.NL;
		$body .= 'Maar het zou ook zomaar een vredelievende gebruiker kunnen zijn';
		$body .= ' die door een hacker in een pishing aanval geleidt wordt.'.NL;
		
		$body .= self::add_server_info($exception);
		load::helper('email');
		email::send(WEBMASTER, 'Hack poging', $body);
	}
	else {
		echo '[hack] ';
		if (is_string($exception)) {
			echo $exception.NLd;
		}
		else {
			echo $exception->getMessage().NLd;
			echo $exception.NLd;
		}
	}
	
	// show a generic 500 to the user, but skip sending another email
	load::error_500($e=false, $skip_mail=true); // and DON'T pass the exception info
	exit;
}

/*------------------------------------------------------------------------------
	collect server information
------------------------------------------------------------------------------*/
private static function add_server_info($exception=false, $type=false) {
	// start with a fresh sheet
	$info = NL;
	
	// add exception
	if ($exception) {
		$info .= NL.'-----'.NL;
		if (is_object($exception)) {
			$info .= 'Exception #'.$exception->getCode().' *'.$exception->getMessage().'*'.NL;
			$info .= 'in '.$exception->getFile().' @ '.$exception->getLine().NL;
			$info .= NL;
			$info .= 'Exception trace:'.NL;
			$info .= $exception->getTraceAsString().NL;
			$info .= NL;
			$info .= 'Previous exception:'.NL;
			#$info .= $exception->getPrevious().NL;
		}
		elseif (is_string($exception)) {
			$info .= 'Error: *'.$exception.'*'.NL;
		}
	}

	// add mysql info
	$all_queries = false;
	if (class_exists('mysql', $autoload=false) && mysql::$latest_query) {
		$info .= NL.'-----'.NL;
		$info .= 'Last MySQL query: '.mysql::$latest_query.NL;
		
		// store original request queries now
		// before own queries (for user info below) are added without any usefull information
		$all_queries = mysql::$all_queries;
	}
	
	// add the running environment
	$info .= NL.'-----'.NL;
	$request = (defined('REQUEST') && REQUEST) ? REQUEST : '-';
	$controller = (defined('CONTROLLER') && CONTROLLER) ? CONTROLLER : '-';
	$arguments = (defined('CONTROLLER') && CONTROLLER) ? json_decode(ARGUMENTS, true) : '-';
	$info .= 'REQUEST: /'.$request.NL;
	$info .= 'CONTROLLER: *'.$controller.'*'.NL;
	$info .= 'ARGUMENTS: '.var_export($arguments, $output=true).NL;
	if (!empty($_SERVER['HTTP_REFERER'])) {
		$info .= 'REFERER: '.$_SERVER['HTTP_REFERER'].NL;
	}
	else {
		$info .= 'REFERER: -'.NL;
	}
	
	// collect some more info about the person
	if ($type == 'mysql') {
		// skip if mysql failed earlier
		$info .= self::add_browser_info($mysql_trouble=true);
	}
	else {
		try {
			$info .= self::add_user_info();
		}
		catch (Exception $e) {
			// else, get some user agent stuff ourselves (copy-paste from sessions_model)
			$info .= self::add_browser_info();
		}
	}
	
	// add all mysql queries
	if ($all_queries) {
		$info .= NL.'-----'.NL;
		$info .= 'All MySQL queries from this request:'.NL;
		foreach ($all_queries as $query) {
			// limit the queries length
			// on mysql queries the exception is mailed to the webmaster
			// if there is another error, it will list all previous mysql queries
			// including the one which stored a whole exception mail in the database
			// if there are multiple error queries after each other
			//   the whole arguments field gets too full and too much escaped
			//   \\\\\\\\\\\\\\\\nCONTROLLER: *pg\\\\\\\\\\\\\\\\\\\\\/deliver*\\\\\\\\
			// to save the actual exception emails from failing, trim the queries
			if (mb_strlen($query) > 2048) {
				$query = mb_substr($query, 0, 2048);
			}
			
			$info .= '- '.$query.NL;
		}
	}
	
	// strip passwords from POST data
	if (strpos($controller, 'login') || strpos($controller, 'password')) {
		if (isset($_POST['password']))  $_POST['password']  = '(wachtwoord niet getoond)';
		if (isset($_POST['new']))       $_POST['new']       = '(wachtwoord niet getoond)';
		if (isset($_POST['current']))   $_POST['current']   = '(wachtwoord niet getoond)';
	}
	
	// add user requests
	$info .= NL.'-----'.NL;
	$info .= 'GET: '.var_export($_GET, true).NL;
	$info .= 'POST: '.var_export($_POST, true).NL;
	$info .= 'COOKIE: '.var_export($_COOKIE, true).NL;
	$info .= 'SERVER: '.var_export($_SERVER, true).NL;
	
	// end the message
	$info .= NL.'-----'.NL;
	$info .= 'eom';
	
	return $info;
}

private static function add_user_info() {
	$session = load::model('session');
	$info = NL.'-----'.NL;
	
	// it is one of our users
	if ($session->user_id) {
		$info .= '*Gebruiker* (#'.$session->user_id.')';
		try {
			$user = load::model('user', $session->user_id);
			$user_email = $user->get_property('emailaddress');
			$info .= ' '.$user_email.'.'.NL;
			
			// a photographer
			$photographers = load::model('photographers');
			$pg_info = $photographers->find_by_userid($session->user_id, $extended=true);
			if ($pg_info) {
				$info .= 'En *fotograaf* (#'.$pg_info['id'].')';
				$info .= ' *'.$pg_info['first_name'].' '.$pg_info['last_name'].'*';
				if ($pg_info['phone']) {
					$info .= ', '.$pg_info['phone'];
				}
				$info .= '.'.NL;
			}
		}
		catch (Exception $e) {
			// skip silently
		}
	}
	
	// add user agent stuff
	$info .= 'Session #'.$session->id.NL;
	$info .= 'Browser: ';
	$info .= $session->get_property('challenge_data'); // just directly as json
	$info .= NL;
	
	return $info;
}

private static function add_browser_info($mysql_trouble=false) {
	$user_agent = array(
		'ip_address'      => (isset($_SERVER['REMOTE_ADDR'])) ? $_SERVER['REMOTE_ADDR'] : '',
		'user_agent'      => (isset($_SERVER['HTTP_USER_AGENT'])) ? $_SERVER['HTTP_USER_AGENT'] : '',
		
		'accept_content'  => (isset($_SERVER['HTTP_ACCEPT'])) ? $_SERVER['HTTP_ACCEPT'] : '',
		'accept_language' => (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : '',
		'accept_encoding' => (isset($_SERVER['HTTP_ACCEPT_ENCODING'])) ? $_SERVER['HTTP_ACCEPT_ENCODING'] : '',
		'accept_charset'  => (isset($_SERVER['HTTP_ACCEPT_CHARSET'])) ? $_SERVER['HTTP_ACCEPT_CHARSET'] : '',
	);
	
	$info = NL.'-----'.NL;
	if ($mysql_trouble) {
		$info .= 'Aangezien MySQL problemen heeft; geen session, wel een browser:'.NL;
	}
	else {
		$info .= 'Geen session, wel een browser:'.NL;
	}
	
	$info .= json_encode($user_agent).NL; // json is easier for now
	
	return $info;
}

/*------------------------------------------------------------------------------
	mails about cron(jobs)
	
	don't use the add_server_info as that might not be available
------------------------------------------------------------------------------*/
public static function mail_cron_job($job_info, $e) {
	$short_exception_message = $e->getMessage();
	if (mb_strlen($short_exception_message > 60)) {
		$short_exception_message = mb_substr($short_exception_message, 0, 60).'...';
	}
	
	$subject = 'Mislukte cron! '.$job_info['filename'].'::'.$job_info['function']
		.' => "'.$short_exception_message.'"';
	
	$body = 'Er ging iets mis bij het uitvoeren van de cronjob op *'.APP_DOMAIN.'*.'.NL
		.NL
		.'Cronjob:'.NL
		.'- cron id: '.$job_info['id'].NL
		.'- time: '.$job_info['start_from'].NL
		.'- file: *'.$job_info['filename'].'*'.NL
		.'- func: *'.$job_info['function'].'*'.NL
		.'- args: '.$job_info['arguments'].NL
		.NL
		.'Exception:'.NL
		.'- msg: *'.$e->getMessage().'*'.NL
		.'- code: '.$e->getCode().NL
		.'- file: '.$e->getFile().NL
		.'- line: '.$e->getLine().NL
		.NL
		.'Exception trace:'.NL
		.$e->getTraceAsString().NL
		.NL
		.'Previous exception:'.NL
		.$e->getPrevious().NL
		.NL
		.'-----'.NL
		.'eom';
	
	load::helper('email');
	email::send(WEBMASTER, $subject, $body);
}

public static function mail_cron_generic($content) {
	$body = 'Tijdens het uitvoeren van de cronjobs op *'.APP_DOMAIN.'*'
		.' zou er geen output mogen zijn. Toch was er de volgende output:'.NL
		.NL
		.'-----'.NL
		.$content.NL
		.'-----'.NL
		.NL
		.'eom';
	
	load::helper('email');
	email::send(WEBMASTER, 'Fout tijdens cron!', $body);
}

}
?>