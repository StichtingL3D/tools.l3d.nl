<?php
/*------------------------------------------------------------------------------
	cronjob: generic
	runs when triggered by a controller
	
	for now only implements an email cronjob
------------------------------------------------------------------------------*/

class generic_cronjob {

// send out an email with specified template
public static function mail($to, $subject, $body, $other_recipients=false) {
	if (is_array($body)) {
		$body = mustache_tpl::parse_email($body['template'], $body['data']);
	}
	
	email::send($to, $subject, $body, $attachm=false, $other_recipients);
}

}
?>