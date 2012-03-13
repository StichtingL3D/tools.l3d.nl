<?php
/*------------------------------------------------------------------------------
	cron: executing tasks at a later moment
------------------------------------------------------------------------------*/

class cron {

/*------------------------------------------------------------------------------
	schedule a new cronjob, at a specified start moment
	allows $start to be natural language ('next night', etc)
------------------------------------------------------------------------------*/
public static function schedule($start, $call, $data=false) {
	if (is_int($start) == false) {
		$timestamp = strtotime($start);
		if ($timestamp == false) {
			throw new Exception('failed to compute a timestamp for cron usage from "'.$start.'"', 500);
		}
		$start = $timestamp;
	}
	
	list($class, $function) = explode('::', $call);
	
	$cronjobs = load::model('cronjobs');
	$cronjobs->add($start, $class, $function, $data);
}

/*------------------------------------------------------------------------------
	schedule a new cronjob, to be executed at the next cron run
------------------------------------------------------------------------------*/
public static function execute($call, $data=false) {
	self::schedule(0, $call, $data);
}

/*------------------------------------------------------------------------------
	send an email via cron
------------------------------------------------------------------------------*/
public static function mail($to, $subject, $body, $other_recipients=false, $start=0) {
	// arguments for the mail cronjob
	$data_for_cronjob = array(
		'to' => $to,
		'subject' => $subject,
		'body' => $body,
		'others' => $other_recipients,
	);
	
	self::schedule($start, 'generic::mail', $data_for_cronjob);
}

}
?>