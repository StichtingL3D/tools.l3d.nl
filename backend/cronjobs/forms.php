<?php
/*------------------------------------------------------------------------------
	cronjob: forms
	runs every night at 2 o'clock
	runs every week, saturday at 12 o'clock
	runs when triggered by errors in a form submission
	
	* cleanup old forms tokens
	* left forms tokens as statistics
	* users encounter form errors
------------------------------------------------------------------------------*/

class forms_cronjob {

// cleanup old form-tokens
public static function cleanup_tokens() {
	// delete old form-tokens
	$forms = load::model('forms');
	$forms->cleanup();
	
	// schedule next run
	cron::schedule('tomorrow +2 hours', 'forms::cleanup_tokens');
}

// mail about left (waiting) forms, as statistics
public static function weekly_stats() {
	$forms = load::model('forms');
	$left_forms = $forms->cleanup_detect();
	
	if (!empty($left_forms['waiting']) || !empty($left_forms['errors'])) {
		// reformat
		foreach ($left_forms['waiting'] as &$form_info) {
			$form_info = '- '.$form_info['controller'].' ('.$form_info['amount'].'x)';
		}
		$left_forms['waiting'] = implode(NL, $left_forms['waiting']);
		
		foreach ($left_forms['errors'] as &$form_info) {
			$form_info = '- '.$form_info['controller'].' ('.$form_info['amount'].'x)';
		}
		$left_forms['errors'] = implode(NL, $left_forms['errors']);
		
		// mail
		$email_tpl = mustache_tpl::parse_email('forms/email_left_forms', $left_forms);
		email::send(WEBMASTER, 'Formulier statistieken', $email_tpl);
	}
	
	// schedule next run
	cron::schedule('next Saturday +12 hours', 'forms::weekly_stats');
}

// mail when people have trouble with a form, directly when they happen as support
public static function notify_on_error($form_name, $time, $post, $errors, $rules, $user, $pg) {
	// reformat most arrays
	foreach ($post as $key => &$value) {
		$value = "\t".$key.': '.$value;
	}
	foreach ($errors as $key => &$value) {
		$value = "\t".$key.': '.$value;
	}
	foreach ($rules as $key => &$value) {
		$single_rules = implode(', ', $value);
		$value = "\t".$key.': '.$single_rules;
	}
	
	if ($user) {
		$user = array(
			'user_id'	=> $user['id'],
			'email'		=> $user['emailaddress'],
		);
		foreach ($user as $key => &$value) {
			$value = "\t".$key.': '.$value;
		}
	}
	else {
		$user = array("\t".'geen gebruiker');
	}
	
	// reformat photographer
	if ($pg) {
		$places = json_decode(PLACES, true);
		$pg = array(
			'name'		=> '*'.$pg['first_name'].' '.$pg['last_name'].'*',
			'phone'		=> (empty($pg['phone']) ? '-' : $pg['phone']),
			'profile'	=> $places['www'].'fotograaf/'.$pg['profile_vanity'] . (($pg['profile_active']) ? ' (inactief)' : ''),
			'since'		=> text::dutch_dates(date('j F Y', $pg['active_since'])),
			'views'		=> $pg['view_count'],
		);
		foreach ($pg as $key => &$value) {
			$value = "\t".$key.': '.$value;
		}
	}
	else {
		$pg = array("\t".'geen fotograaf');
	}
	
	// send the mail
	$data = array(
		'name'		=> $form_name,
		'time'		=> date('H:i', $time),
		'post'		=> implode(NL, $post),
		'errors'	=> implode(NL, $errors),
		'rules'		=> implode(NL, $rules),
		'user'		=> implode(NL, $user),
		'pg'		=> implode(NL, $pg),
	);
	$email_tpl = mustache_tpl::parse_email('forms/email_form_errors', $data);
	email::send('hallo@citysessies.nl', 'Formulier problemen', $email_tpl);
}

}
?>