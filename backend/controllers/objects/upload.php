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

$file_type = false;
$file_path = false;
$file_name = false;
$file_ext = false;

if (!empty($_POST['type'])) {
	$rules = array('type'=>array('string'));
	$form = forms::check('objects/upload', $rules);
	$allowed_types = array_flip(array('avatars','models','seqs','sounds','textures'));
	if (isset($allowed_types[$form['type']]) == false) {
		show_error('type', false, false);
		exit;
	}
	
	$file_type = $form['type'];
}

try {
	$fileinfo = upload::check($mime=false, $min='1', $max='1048576'); // 1mb
	$file_path = $fileinfo['path'];
	$file_name = $fileinfo['name'];
}
catch (Exception $e) {
	show_error('upload', $e);
	exit;
}

$file_ext = substr($file_name, strrpos($file_name, '.')+1);

// automagically determine the objectpath type
if ($file_type == false) {
	#TODO: unzip zipped files
	
	$extension_to_type = array(
		'zip'  => 'models',
		'rwx'  => 'models',
		'cob'  => 'models',
		'scn'  => 'models',
		'x'    => 'models',
		'mp3'  => 'sounds',
		'wav'  => 'sounds',
		'midi' => 'sounds',
		'jpg'  => 'textures',
		'jpeg' => 'textures',
		'png'  => 'textures',
		'gif'  => 'textures',
		'tiff' => 'textures',
		'bmp'  => 'textures',
	);
	
	if (isset($extension_to_type[$file_ext])) {
		$file_type = $extension_to_type[$file_ext];
	}
	else {
		// default fallback
		$file_type = 'models';
	}
}

// do the zipping for the user
$unzipped_extensions = array_flip(array('rwx', 'cob', 'scn', 'x', 'bmp'));
if (isset($object_extensions[$file_ext])) {
	#TODO: zip the file
}

$file_name = preg_replace('/[^a-z0-9._-]+/', '', strtolower($file_name));

/*------------------------------------------------------------------------------
	move to objectpath
------------------------------------------------------------------------------*/

try {
	$objectpath = new objectpath('bbcn');
	$objectpath->add_file($file_type, $file_path, $file_name);
}
catch (Exception $e) {
	show_error('ftp', $e);
	exit;
}

/*------------------------------------------------------------------------------
	connect to user
------------------------------------------------------------------------------*/

$objectpath_id = $objectpath->get_property('id'); // index_key is domain
$citizen_id = $session->user_id;

$objects = new object();
$object_id = $objects->add($file_type, $file_name, $objectpath_id, $citizen_id);

load::redirect('objecten?klaar='.$object_id);
