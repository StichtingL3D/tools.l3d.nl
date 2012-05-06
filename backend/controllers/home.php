<?php
/*------------------------------------------------------------------------------
	home: redirecting to a users homepage
------------------------------------------------------------------------------*/

$page = input::secure(REQUEST, $silent=true);

try {
	$session = load::model('session');
	$user = load::model('user', $session->user_id);
	$user->goto_home();
}
catch (Exception $e) {
	// not logged in
	load::redirect('');
}

/*
load::helper('clickmodel');
$clickmodel = new clickmodel();

$clickmodel->title = 'Kies je CitySessie';
$clickmodel->targetgroup = 'iedereen';
$clickmodel->c2a = array('sessie boeken (in Amsterdam, of een andere)', '/search');
$clickmodel->c2a_others = array(
	'over cs' => '/over',
	'aanmelden' => '/pg/register',
	'inloggen' => '/inloggen',
	'coupon invoeren' => '/search?coupon=#',
	'kadobon kopen' => '/book/gift',
	'contact opnemen' => '/contact',
);
$clickmodel->content = 'plaatsen, featured lijstjes met foto\'s';
$clickmodel->redaction = 'door cs op actief/featured gezet';

$clickmodel->release = 'beta 1';
$clickmodel->process = '';
$clickmodel->comment = '';

$clickmodel->url = '/';
$clickmodel->url_alias = '';
$clickmodel->auth = 'geen';

$clickmodel->show();
*/
?>
