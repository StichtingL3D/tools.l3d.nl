<?php
try {
	$sessions = load::model('sessions');
	$sessions->delete_current();
	
	// goodbye!
	$languages = load::config('logout', 'languages');
	$greetings = load::config('logout', 'greetings');
	
	$total = count($languages);
	$lang_key = array_rand($languages);
	$language = $languages[$lang_key];
	$greeting = $greetings[$lang_key];
	
	header($_SERVER['SERVER_PROTOCOL'].' 732 Fucking Unicode');
	$data = array('greeting'=>$greeting, 'language'=>$language);
	page::show('user/loggedout', $data);
}
catch (Exception $e) {
	load::error_500($e);
}
?>