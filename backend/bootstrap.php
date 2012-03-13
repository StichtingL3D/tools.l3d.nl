<?php
/*------------------------------------------------------------------------------
	starting up our environment
	used by index.php and backend/helpers/cron.php
------------------------------------------------------------------------------*/

/*------------------------------------------------------------------------------
	globalization
------------------------------------------------------------------------------*/
define('NL', "\n");
define('NLd', nl2br(NL));
define('DEFAULT_CHARSET', 'UTF-8');
mb_internal_encoding('UTF-8');
date_default_timezone_set('Europe/Amsterdam');

/*------------------------------------------------------------------------------
	environmental check
------------------------------------------------------------------------------*/

// we probably got the private-path from index or cron
if (isset($private) == false) {
	$private = realpath(dirname(__FILE__).'/..');
}

// get the public-path and the environment
if (file_exists($private.'/public_html')) {
	$public = realpath($private.'/public_html');
	
	define('ENVIRONMENT', 'development');
}
else {
	#$public = realpath(substr($private, 0, strpos($private, '-backend')));
	$public = realpath($private.'/../public_html/upload');
	
	$release = basename($public);
	$release = ($release == 'DEFAULT') ? 'production' : $release;
	define('ENVIRONMENT', $release);
}

define('MAINTENANCE', file_exists($public.'/maintenance.txt'));

/*------------------------------------------------------------------------------
	whoami
------------------------------------------------------------------------------*/

$possible_domains = array(
	'production'	=> 'tools.l3d.nl',
	'test'			=> 'test-tools.l3d.nl',
	'development'	=> 'dev-tools.l3d.nl',
);

define('APP_DOMAIN', $possible_domains[ENVIRONMENT]);
define('APP_NAME', str_replace(array('www', '.'), '', APP_DOMAIN));

define('WEBMASTER', 'webmaster@l3d.nl');

/*------------------------------------------------------------------------------
	know when to say what
------------------------------------------------------------------------------*/

// hide errors on production
ini_set('display_errors', 0);
error_reporting(0);
if (ENVIRONMENT != 'production') {
	ini_set('display_errors', 1);
	error_reporting(-1);
}

// handle errors as exceptions
function exception_error_handler($level, $message, $file=false, $line=false) {
	throw new ErrorException($message, 0, $level, $file, $line);
}
set_error_handler("exception_error_handler");

// don't tell the whole world we're running php version x
if (function_exists('header_remove')) {
	header_remove('X-Powered-By');
}
else {
	header('X-Powered-By:');
}

/*------------------------------------------------------------------------------
	a place to stay
------------------------------------------------------------------------------*/

$places = array(
	'private'      => $private.'/',
	'backend'      => $private.'/backend/',
	'cache'        => $private.'/cache/',
	'data'         => $private.'/data/'.ENVIRONMENT.'/',
	
	'public'       => $public.'/',
	'frontend'     => $public.'/frontend/',
	
	'www'          => 'http://'.APP_DOMAIN.'/',
	'www_frontend' => 'http://'.APP_DOMAIN.'/frontend/',
);

define('PLACES', json_encode($places));

?>