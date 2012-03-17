<?php
/*------------------------------------------------------------------------------
	find our way to the bootstrapper
------------------------------------------------------------------------------*/

$public = dirname(__FILE__);
if (file_exists($public.'-backend')) {
	$private = $public.'-backend';
}
else {
	$private = realpath($public.'/..');
}

require_once($private.'/backend/bootstrap.php');
require_once($places['backend'].'load.php');

/*------------------------------------------------------------------------------
	dating for requests, finding an accompanying controller
------------------------------------------------------------------------------*/
$default_controller = 'user/login';
$request = '';
$controller = '';
$arguments = array();

// get the request (and controller for empty requests)
if (isset($_SERVER['REQUEST_URI']) && $_SERVER['REQUEST_URI'] == '/') {
	$controller = $default_controller;
}
else {
	if (!empty($_SERVER['PATH_INFO'])) {
		$request = trim($_SERVER['PATH_INFO'], '/');
	}
	elseif (!empty($_SERVER['REQUEST_URI'])) {
		$request = trim($_SERVER['REQUEST_URI'], '/');
		
		// cut off the request arguments
		$args_pos = strpos($request, '?');
		if ($args_pos !== false) {
			$request = substr($request, 0, $args_pos);
		}
	}
	$request = strtolower(preg_replace('/[^a-zA-Z0-9+_.\/-]+/u', '', $request));

	if (empty($request)) {
		$controller = $default_controller;
	}
}

// find our controller
if (empty($controller)) {
	try {
		// here the magic happens; transforming a request uri to a controller class
		require_once($places['backend'].'urls.php');
		request2controller($request, $controller, $arguments);
	}
	catch (Exception $e) {
		$url_vars = array(
			'request' => $request,
			'controller' => $controller,
			'arguments' => $arguments,
		);
		load::error_404($e, $skip_mail=false, $url_vars);
		exit;
	}
}

/*------------------------------------------------------------------------------
	get to work
------------------------------------------------------------------------------*/
$controller_path = $places['backend'].'controllers/'.$controller.'.php';
if (file_exists($controller_path)) {
	define('REQUEST', $request); // use $_GET to get request arguments after the ?
	define('CONTROLLER', $controller);
	define('ARGUMENTS', json_encode($arguments));
	
	try {
		require_once($controller_path);
	}
	catch (Exception $e) {
		load::error_500($e);
	}
}
elseif ($controller == '404' || $controller == '500' || $controller == 'maintenance' || $controller == 'future') {
	define('REQUEST', $request);
	define('CONTROLLER', $controller);
	define('ARGUMENTS', json_encode($arguments));
	require_once('error_'.$controller.'.php');
}
else {
	load::error_500('defined controller file does not exist!');
}

/*------------------------------------------------------------------------------
	nothing to see anymore
------------------------------------------------------------------------------*/
exit;
?>