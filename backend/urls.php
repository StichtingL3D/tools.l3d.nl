<?php
// inspiration from http://code.google.com/p/cobweb/wiki/URLDispatch
// and http://stackoverflow.com/questions/1511091/php-url-routing-kind-of-like-django

function request2controller($request, &$controller, &$arguments) {
	// finding a controller happy to receive this request
	$rules = array();
	
	/*--- development ---*/
	
	if (ENVIRONMENT == 'development') {
	}
	
	/*--- test ---*/
	
	if (ENVIRONMENT != 'production') {
	}
	
	/*--- production ---*/
	
	// testing
	$rules['{^404$}'] = '404';
	$rules['{^500$}'] = '500';
	
	// basics
	$rules['{^home$}'] = 'home';
	
	// uploading
	$rules['{^upload$}'] = 'upload/upload';
	
	// user accounts
	$rules['{^inloggen$}'] = 'user/login';
	$rules['{^uitloggen$}'] = 'user/logout';
	#$rules['{^(?<action>activeren)/(?<unique_id>[a-zA-Z0-9]+)$}'] = 'user/password';
	#$rules['{^wachtwoord/(?<action>veranderen)$}'] = 'user/password';
	#$rules['{^wachtwoord/(?<action>deactiveren)$}'] = 'user/password';
	#$rules['{^wachtwoord/(?<action>herstellen)(/(?<unique_id>[a-zA-Z0-9]+))?$}'] = 'user/password';
	
	/*--- static urls ---*/
	
	if (isset($rules[ '{^'.$request.'$}' ]) && preg_match('{^[a-z0-9/]+$}', $request)) {
		$controller = $rules[ '{^'.$request.'$}' ];
		$arguments = array();
		return;
	}
	
	/*--- listed urls ---*/
	// redirect to the right controller based on cached lists
	// none at the moment
	
	/*--- dynamic urls ---*/
	
	foreach ($rules as $rule => $call) {
		if (preg_match($rule.'u', $request, $matches)) {
			$controller = $call;
			
			unset($matches[0]);
			$arguments = $matches;
			
			return;
		}
	}
	
	throw new Exception('no controller found!');
}
?>