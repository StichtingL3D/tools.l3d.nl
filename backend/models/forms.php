<?php
/*------------------------------------------------------------------------------
	forms
------------------------------------------------------------------------------*/

class forms_model extends models {

protected $table = 'forms';

public function add($token, $controller, $ajax=false) {
	// optionally, add the current session id
	try {
		$session = load::model('session');
		$session_id = $session->id;
	}
	catch (Exception $e) {
		$session_id = '';
	}
	
	$new_data = array(
		'id' => $token,
		'controller' => $controller,
		'sent_at' => time(),
		'session_id' => $session_id,
	);
	if ($ajax) {
		$new_data['status'] = 'ajax';
	}
	
	parent::insert($new_data);
	
	return $token;
}

public function check($token, $controller) {
	$sql = "SELECT `id` FROM `".$this->table."` WHERE
		`id` = '%s' AND (`status` = 'waiting' OR `status` = 'ajax') AND `controller` = '%s';";
	
	$check = mysql::select('field', $sql, $token, $controller);
	return (bool)$check;
}

// select left forms (still waiting)
public function cleanup_detect() {
	$forms = array();
	
	// forms that are left waiting
	$min_age = time() - (60*60*24); // after one day
	$max_age = time() - (60*60*24*8); // already mailed (once a week + one day from above)
	$sql = "
		SELECT
			`controller`,
			COUNT(*) AS 'amount'
		FROM `".$this->table."`
		WHERE
			`status` = 'waiting'
			AND
			`sent_at` < %d
			AND
			`sent_at` > %d
		GROUP BY `controller`
		ORDER BY `amount` DESC;
	";
	$forms['waiting'] = mysql::select('array', $sql, $min_age, $max_age);
	
	// group together controllers
	$forms_total = count($forms['waiting']);
	for ($i=0; $i<$forms_total; $i++) {
		$form = $forms['waiting'][$i];
		
		// controllers with ids
		if (preg_match('/[0-9]+/', $form['controller'])) {
			$generic_name = preg_replace('/[0-9]+/', '#', $form['controller']);
			
			if (empty($forms['waiting'][$generic_name])) {
				$forms['waiting'][$generic_name] = array(
					'controller' => $generic_name,
					'amount' => 0,
				);
			}
			$forms['waiting'][$generic_name]['amount'] += $form['amount'];
			
			unset($forms['waiting'][$i]);
		}
	}
	$forms['waiting'] = array_values($forms['waiting']);
	
	// errors with tokens
	$min_age = time() - (60*60*24); // after one day
	$max_age = time() - (60*60*24*8); // already mailed (once a week + one day from above)
	$sql = "
		SELECT
			`controller`,
			`status`,
			COUNT(*) AS 'amount'
		FROM `".$this->table."`
		WHERE
			`status` != 'waiting'
			AND
			`status` != 'done'
			AND
			`sent_at` < %d
			AND
			`sent_at` > %d
		GROUP BY `controller`, `status`
		ORDER BY `amount` DESC;
	";
	$forms['errors'] = mysql::select('array', $sql, $min_age, $max_age);
	
	return $forms;
}

// remove old forms that are done, have an error, or are still waiting
public function cleanup() {
	// ajax since last day
	$one_day = time() - (60*60*24);
	$sql = "DELETE FROM `".$this->table."` WHERE
		`status` = 'ajax' AND `sent_at` < %d;";
	mysql::query($sql, $one_day);
	
	// done since last day
	$one_day = time() - (60*60*24);
	$sql = "DELETE FROM `".$this->table."` WHERE
		`status` = 'done' AND `sent_at` < %d;";
	mysql::query($sql, $one_day);
	
	// error since last week
	$one_week = time() - (60*60*24*7);
	$sql = "DELETE FROM `".$this->table."` WHERE
		`status` != 'waiting' AND `sent_at` < %d;";
	mysql::query($sql, $one_week);
	
	// waiting since last week
	$one_month = time() - (60*60*24*7);
	$sql = "DELETE FROM `".$this->table."` WHERE
		`status` = 'waiting' AND `sent_at` < %d;";
	mysql::query($sql, $one_month);
}

}
?>