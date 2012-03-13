<?php
/*------------------------------------------------------------------------------
	cronjobs
------------------------------------------------------------------------------*/

class cronjobs_model extends models {

protected $table = 'cronjobs';
private $max_concurrent_jobs = 5;

/*------------------------------------------------------------------------------
	jobs to executed now
------------------------------------------------------------------------------*/
public function waiting() {
	$where = array(
		array("start_from", "<", time()),
		"AND",
		array("status", 'waiting'),
	);
	$limit = $this->max_concurrent_jobs;
	
	return $this->get_all($keys="*", $where, $group=false, $order=false, $limit);
}

/*------------------------------------------------------------------------------
	adding new jobs
------------------------------------------------------------------------------*/
public function add($start_from, $filename, $function, $arguments=false) {
	if ($start_from == false || $start_from == 'now') {
		$start_from = 0;
	}
	
	$new_data = array(
		'start_from' => $start_from,
		'filename' => $filename,
		'function' => $function,
	);
	if (is_array($arguments)) {
		$new_data['arguments'] = json_encode($arguments);
	}
	
	return parent::insert($new_data);
}

/*------------------------------------------------------------------------------
	check for too long running crons and remove old jobs
------------------------------------------------------------------------------*/
public function cleanup() {
	// old active crons
	$sql = "SELECT *, CONCAT(SUBSTRING(`arguments`,1,100), '...') AS 'arguments_short' FROM `".$this->table."` WHERE (`status` = 'starting' OR `status` = 'running') AND `start_from` < %d;";
	$active_expiration = time() - (60*15); // 15 minutes - 3 cron executions
	
	$still_active_crons = mysql::select('array', $sql, $active_expiration);
	if ($still_active_crons) {
		$email_body = array(
			'template' => 'cronjobs/still_active_cronjobs',
			'data' => array(
				'still_active_cronjobs' => array_values($still_active_crons),
			),
		);
		cron::mail(WEBMASTER, 'Sommige cronjobs zijn vastgelopen', $email_body);
	}
	
	// old done crons
	$sql = "DELETE FROM `".$this->table."` WHERE `status` = 'done' AND `start_from` < %d;";
	$when_is_old = time() - (60*60*24*7); // one week
	
	mysql::query($sql, $when_is_old);
	$affected = mysql::$affected_rows;
	return $affected;
}

}
?>