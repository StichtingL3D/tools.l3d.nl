<?php
/*------------------------------------------------------------------------------
	upload: uploading files to an objectpath
	removing the need for schools to open up port 21
	
	error types:
	- type:   wrong objectpath type (messing with selectbox)
	- upload: file upload failed, unknown who's fault it is
	- ftp:    failure during ftp, our fault
	- move:   already exists, or our fault
	
	TODO:
	- check for the corrent mimetype
	- use ssh2_sftp() for sftp (needs server install)
	- create screenshot of objects as well
------------------------------------------------------------------------------*/

$session = load::model('session', $else='login');
$session->require_level('citizen+', $else='login');

if (empty($_FILES)) {
	load::redirect('objecten');
	exit;
}

function show_error($error_type, $exception=false) {
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
	show_error('type');
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

$config = new config('upload');
$remote_path = $config['path'].$form['type'].'/';
$remote_file = $safe_filename;
$local_file = $fileinfo['path'];

try {
	$remote_connection = ftp_connect($config['host']);
	if ($remote_connection == false) {
		throw new Exception('ftp fails to connect');
	}
	
	$remote_login_check = ftp_login($remote_connection, $config['user'], base64_decode($config['pass']));
	if ($remote_login_check == false) {
		throw new Exception('ftp fails to login');
	}
	
	#ftp_pasv($remote_connection, true); // remedy for all kind of ftp errors, see php.net/function.ftp-put.php.html#90518 and more
	
	$remote_chdir_check = ftp_chdir($remote_connection, $remote_path);
	if ($remote_chdir_check == false) {
		throw new Exception('ftp fails to change directory');
	}
}
catch (Exception $e) {
	show_error('ftp', $e);
	exit;
}

try {
	$remote_exists_check = ftp_size($remote_connection, $remote_file);
	if ($remote_exists_check > 0) {
		throw new Exception('file already exists');
	}
	
	$remote_upload_check = ftp_put($remote_connection, $remote_file, $local_file, FTP_BINARY);
	if ($remote_upload_check == false) {
		throw new Exception('ftp fails upload');
	}
	
	$remote_close_check = ftp_close($remote_connection);
	if ($remote_close_check == false) {
		throw new Exception('file fails to close');
	}
}
catch (Exception $e) {
	show_error('move', $e);
	exit;
}

/*------------------------------------------------------------------------------
	connect to user
------------------------------------------------------------------------------*/

$objectpath = new objectpath('bbcn');

$new_object = array(
	'type' => $form['type'],
	'filename' => $remote_file,
	'objectpath_id' => $objectpath->get_property('id'), // index_key is domain
	'citizen_id' => $session->user_id,
);

$objects = new object();
$object_id = $objects->insert($new_object);

load::redirect('objecten?klaar='.$object_id);
