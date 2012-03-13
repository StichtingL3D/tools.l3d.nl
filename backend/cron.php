<?php
/*------------------------------------------------------------------------------
	cron: executing tasks at a later moment
	
	this file is the entry point for the system's cronjob
	install instructions are in INSTALL.markdown
------------------------------------------------------------------------------*/

/*------------------------------------------------------------------------------
	save all output and possible errors for later
------------------------------------------------------------------------------*/
ob_start();

/*------------------------------------------------------------------------------
	startup the environment
------------------------------------------------------------------------------*/
$private = realpath(dirname(__FILE__).'/..');
require_once($private.'/backend/bootstrap.php');
require_once($private.'/backend/load.php');

/*------------------------------------------------------------------------------
	check if there is work to do
------------------------------------------------------------------------------*/
try {
	$cronjobs = load::model('cronjobs');
	$jobs_to_execute = $cronjobs->waiting();
	
	// execute cronjobs
	if ($jobs_to_execute) {
		foreach ($jobs_to_execute as $job_id => $job_info) {
			
			try {
				$job = load::model('cronjob', $job_id);
				$job->execute();
			}
			catch (Exception $e) {
				if ($job) {
					$job->update_property('status', 'error');
					$job->update_property('result', $e->getMessage());
				}
				
				// something terrible happened, let the webmaster know
				error::mail_cron_job($job_info, $e);
			}
			
		}
	}
	
	// remove cronjobs marked as done and older than a week
	$cronjobs->cleanup();
}
catch (Exception $e) {
	// let the errors come in the generic output email
	echo $e;
}

/*------------------------------------------------------------------------------
	check for output or errors
------------------------------------------------------------------------------*/
if (ob_get_length()) {
	$output = ob_get_contents();
	error::mail_cron_generic($output);
}
ob_end_clean();

/*------------------------------------------------------------------------------
	tell cron everything went fine
------------------------------------------------------------------------------*/
return 0;
?>