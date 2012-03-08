<?php
/*------------------------------------------------------------------------------
	generic error 404, the page/controller is not found
------------------------------------------------------------------------------*/

// set a default error message
if (!isset($e)) {
	// errors before index.php kicks in
	if ($_SERVER['REDIRECT_STATUS'] != 200 && $_SERVER['SCRIPT_NAME'] == '/error404.php') {
		$e = 'htaccess catched '.$_SERVER['REDIRECT_STATUS'].' for '.$_SERVER['REQUEST_URI'];
	}
	else {
		$e = 'unknown error';
	}
}

// get variables if we're not loaded from index.php directly
// .. or because the controller was not launched
if (!defined('REQUEST') && isset($url_vars['request'])) {
	define('REQUEST', $url_vars['request']);
}
if (!defined('CONTROLLER') && isset($url_vars['controller'])) {
	define('CONTROLLER', $url_vars['controller']);
}
if (!defined('ARGUMENTS') && isset($url_vars['arguments'])) {
	if (!empty($url_vars['arguments'])) {
		$url_vars['arguments'] = array();
	}
	define('ARGUMENTS', json_encode($url_vars['arguments']));
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
header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');

$message = '<!DOCTYPE html><html><head>';
$message .= '<meta http-equiv="Content-Type" content="text/html; charset='.DEFAULT_CHARSET.'">';
$message .= '<meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">';
$message .= '<meta name="viewport" content="width=device-width,initial-scale=1">';
$message .= '<title>Foutje, bedankt! - L3D tools</title>';
$message .= '<title>Niet gevonden - L3D tools</title>';
$message .= '<link rel="shortcut icon" href="'.$places['www'].'favicon.ico" type="image/x-icon">';
$message .= '</head><body>';
$message .= '<h1>Niet gevonden</h1>';
$message .= '<p>Hmm, die pagina kunnen we niet vinden.</p>';
$message .= '<p>Als je denkt dat hier wel iets zou moeten staan, ';
$message .= 'laat het ons weten en <a href="'.$places['www'].'contact">';
$message .= 'neem contact op</a>.</p>';
$message .= '<p>Wil je <a href="javascript:history.back(-1);">terug?</a> Of naar de <a href="'.$places['www'].'">homepage</a>?</p>';
$message .= '</body></html>';

echo $message;

if (ENVIRONMENT != 'development' && defined('SKIP_404_EMAIL') == false && CONTROLLER != '404') {
	try {
		load::helper('error');
		error::mail($e, $message, $type=404);
	}
	catch (Exception $e) {
		// skip silently
	}
}

if (ENVIRONMENT == 'development') {
	if (is_object($e)) {
		echo '<pre style="clear: both;">';
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
		echo ': '.$e->getMessage().'<br>in '.$e->getFile().' @ '.$e->getLine().'</pre>';
		echo '<pre>trace: '.NL.$e->getTraceAsString().'</pre>';
		echo '<pre>previous: ';
		echo ($e->getPrevious()) ? $e->getPrevious() : '-';
		echo '</pre>';
	}
	elseif (is_string($e)) {
		echo '<pre>error message: '.$e.'</pre>';
	}
	
	echo '<pre>mysql: ';
	if (class_exists('mysql', $autoload=false) && mysql::$connection) {
		foreach (mysql::$all_queries as $query) {
			echo NL.'- '.$query;
		}
	}
	else {
		echo '-';
	}
	echo NL.'</pre>';
}

exit;
?>