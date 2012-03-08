<?php
/*------------------------------------------------------------------------------
	generic error 500, something went wrong inside the controller
------------------------------------------------------------------------------*/

// set a default error message
if (!isset($e)) {
	// errors before index.php kicks in
	if ($_SERVER['REDIRECT_STATUS'] != 200 && $_SERVER['SCRIPT_NAME'] == '/error500.php') {
		$e = 'htaccess catched '.$_SERVER['REDIRECT_STATUS'].' at '.$_SERVER['REQUEST_URI'];
	}
	else {
		$e = 'unknown error';
	}
}

// get a natural name of the running environment
if (defined('APP_DOMAIN')) {
	$environment_name = APP_DOMAIN;
}
else {
	$environment_name = htmlspecialchars($_SERVER['SERVER_NAME'], ENT_QUOTES, DEFAULT_CHARSET);
}

// make sure places is known
if (!isset($places) && defined('PLACES')) {
	$places = json_decode(PLACES, true);
}
if (!isset($places)) {
	$public = dirname(__FILE__);
	$private = (file_exists($public.'-backend')) ? $public.'-backend' : realpath($public.'/..');
	include_once($private.'/backend/bootstrap.php');
}

// get the loader
include_once($places['backend'].'load.php');

/*------------------------------------------------------------------------------
	show a message
------------------------------------------------------------------------------*/
header($_SERVER['SERVER_PROTOCOL'].' 500 Internal Server Error');

$message = '<!DOCTYPE html><html><head>';
$message .= '<meta http-equiv="Content-Type" content="text/html; charset='.DEFAULT_CHARSET.'">';
$message .= '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">';
$message .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
$message .= '<title>Foutje, bedankt! - L3D tools</title>';
$message .= '<link rel="shortcut icon" href="'.$places['www'].'favicon.ico" type="image/x-icon">';
$message .= '</head><body>';
$message .= '<h1>Dankjewel!</h1>';
$message .= '<p>Je hebt een foutje gevonden in onze website.</p>';
$message_mailed = '<p>Er is een mail naar onze webbouwers onderweg, we gaan het oplossen! Wil je zo nog eens terug komen?';
$message .= NL.$message_mailed.'</p>';
$message .= '<p>Wil je <a href="javascript:history.back(-1);">terug?</a> Of naar de <a href="'.$places['www'].'">homepage</a>?</p>';
$message .= '</body></html>';

if (ENVIRONMENT != 'development' && defined('SKIP_500_EMAIL') == false && CONTROLLER != '500') {
	try {
		load::helper('error');
		error::mail($e, $message);
	}
	catch (Exception $e) {
		// email failed as well, ask user to send us an email
		// and include some usefull info
		header($_SERVER['SERVER_PROTOCOL'].' 776 Error on the Exception');
		define('NL_MAIL', "\r\n");
		$referer = (!empty($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '-';
		
		$message_failed =
			'Zou je ons willen '
				.'<a href="mailto:'.WEBMASTER.'?subject='.rawurlencode('Foutmelding op '.$environment_name)
				.'&body='.rawurlencode('Ik kwam op de pagina '.$places['www'].REQUEST.' '.NL_MAIL
				.'nadat ik geklikt had op ...'.NL_MAIL
				.'Eigenlijk ben ik op zoek naar ...'.NL_MAIL
				.NL_MAIL
				.'(de volgende informatie is handig voor ons, kun je deze meesturen?)'.NL_MAIL
				.'-----'.NL_MAIL
				.'REQUEST: '.REQUEST.NL_MAIL
				.'REFERER: '.$referer.NL_MAIL
				.'GET: '.var_export($_GET, true).NL_MAIL
				.'POST: '.var_export($_POST, true).NL_MAIL
				.'COOKIE: '.var_export($_COOKIE, true).NL_MAIL
				.'-----')
			.'">mailen</a>? Dan gaan we het oplossen!';
		
		$message = str_replace($message_mailed, $message_failed, $message);
	}
}

echo $message;

if (ENVIRONMENT == 'development') {
	if (is_object($e)) {
		echo '<div class="special_box"><pre style="clear: both;">';
		if (is_callable(array($e, 'getSeverity'))) {
			$severity_code = $e->getSeverity();
			$severity_names = array(
				E_WARNING => 'Warning',
				E_NOTICE => 'Notice',
				E_STRICT => 'Strict',
				E_RECOVERABLE_ERROR => 'Catchable',
			);
			$severity_name = (isset($severity_names[$severity_code])) ? $severity_names[$severity_code] : 'Unknown';
			echo $severity_name;
		}
		else {
			echo '#'.$e->getCode();
		}
		echo ': '.$e->getMessage().'<br>in '.$e->getFile().' @ '.$e->getLine().'</pre></div>';
		echo '<div class="special_box"><pre>trace: '.NL.$e->getTraceAsString().'</pre></div';
		echo '<div class="special_box"><pre>previous: ';
		echo ($e->getPrevious()) ? $e->getPrevious() : '-';
		echo '</pre></div>';
	}
	elseif (is_string($e)) {
		echo '<pre>error message: '.$e.'</pre>';
	}
	
	echo '<div class="special_box"><pre>mysql: ';
	if (class_exists('mysql', $autoload=false) && mysql::$connection) {
		foreach (mysql::$all_queries as $query) {
			echo NL.'- '.$query;
		}
	}
	else {
		echo '-';
	}
	echo NL.'</pre></div>';
}

exit;
?>