<?php
/*------------------------------------------------------------------------------
	upload: uploading files to an objectpath
	removing the need for schools to open up port 21
	
	error types:
	- type:   wrong objectpath type (messing with selectbox)
	- upload: file upload failed, unknown who's fault it is
	- ftp:    failure during ftp, our fault or already exists
	
	TODO:
	- check for the current mimetype
	- use ssh2_sftp() for sftp (needs server install)
	- create screenshot of objects as well
------------------------------------------------------------------------------*/

$session = load::model('session', $else='login');
$session->require_level('citizen+', $else='login');

if (empty($_FILES)) {
	load::redirect('objecten');
	exit;
}

function show_error($error_type, $exception=false, $send_mail=true) {
	#echo $error_type.': <pre>'; print_r($exception); echo '</pre>'.NLd; die;
	error::mail($exception, $error_type);
	load::redirect('objecten?fout='.$error_type);
	exit;
}

/*------------------------------------------------------------------------------
	check the uploaded file
------------------------------------------------------------------------------*/

$rules = array('type'=>array('string'));
$form = forms::check('objects/upload', $rules);
$allowed_types = array_flip(array('avatars','models','seqs','sounds','textures'));
if (isset($allowed_types[$form['type']]) == false) {
	show_error('type', false, false);
	exit;
}

try {
	$fileinfo = upload::check($mime=false, $min='1', $max='1048576'); // 1mb
}
catch (Exception $e) {
	show_error('upload', $e);
	exit;
}

$safe_filename = preg_replace('/[^a-z0-9._-]+/', '', strtolower($fileinfo['name']));

/*------------------------------------------------------------------------------
	move to objectpath
------------------------------------------------------------------------------*/

try {
	$objectpath = new objectpath('bbcn');
	$objectpath->add_file($form['type'], $fileinfo['path'], $safe_filename);
}
catch (Exception $e) {
	show_error('ftp', $e);
	exit;
}

/*------------------------------------------------------------------------------
	connect to user
------------------------------------------------------------------------------*/

$new_object = array(
	'type' => $form['type'],
	'filename' => $safe_filename,
	'objectpath_id' => $objectpath->get_property('id'), // index_key is domain
	'citizen_id' => $session->user_id,
);

$objects = new object();
$object_id = $objects->insert($new_object);

load::redirect('objecten?klaar='.$object_id);
