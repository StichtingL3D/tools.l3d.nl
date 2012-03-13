<?php
/*------------------------------------------------------------------------------
	upload: uploading files to an objectpath, not needing port 21
------------------------------------------------------------------------------*/

#$max_upload_filesize = 0;

#$max_size = $uploader->phpini_to_bytes(ini_get('upload_max_filesize'));
#$tpl->set('max_size', $max_size);

/*------------------------------------------------------------------------------
	show the upload form
------------------------------------------------------------------------------*/

if (empty($_POST)) {
	page::title('Upload');
	page::show('upload/form');
	exit;
}

/*------------------------------------------------------------------------------
	TEMPORARY: quick 'n dirty
	
	TODO:
	- fill in the password
	- test local: https://help.ubuntu.com/10.04/serverguide/C/ftp-server.html
	- use ssh2_sftp() for sftp instead of ftp_ssl_connect()
------------------------------------------------------------------------------*/

echo 'uploading..'.NL;

// check
$allowed_types = array_flip(array('avatars','models','seqs','sounds','textures'));
$type = $_POST['type'];
if (isset($allowed_types[$type]) == false) {
	die('allowed_types => false');
}

$file = reset($_FILES);
if (is_uploaded_file($file['tmp_name']) == false) {
	die('is_uploaded_file => false');
}
if (is_readable($file['tmp_name']) == false) {
	die('is_readable => false');
}

// prepare
$config = load::config('upload');
$remote_path = $config['path'].$type.'/';
$remote_file = basename($file['name']);
if (preg_match('/[^a-z0-9._-]/', $remote_file)) {
	die('preg_match('.$remote_file.') => false, only a-z 0-9 .(dot) _(underscore) -(dash)');
}

$remote_connection = ftp_connect($config['host']); // use ssh2_sftp() for sftp
if ($remote_connection == false) {
	die('ftp_connect => false ('.$remote_connection.')');
}
$remote_login_check = ftp_login($remote_connection, $config['user'], base64_decode($config['pass']));
if ($remote_login_check == false) {
	die('ftp_login => false ('.$remote_login_check.')');
}

// send
#ftp_pasv($remote_connection, true); // remedy for all kind of ftp errors, see php.net/function.ftp-put.php.html#90518 and more
$remote_chdir_check = ftp_chdir($remote_connection, $remote_path);
if ($remote_chdir_check == false) {
	die('ftp_chdir => false ('.$remote_chdir_check.')');
}
$remote_upload_check = ftp_put($remote_connection, $remote_file, $file['tmp_name'], FTP_BINARY);
if ($remote_upload_check == false) {
	die('ftp_put => false ('.$remote_upload_check.')');
}
$remote_close_check = ftp_close($remote_connection);
if ($remote_close_check == false) {
	die('ftp_close => false ('.$remote_close_check.')');
}

echo 'everything ok, '.substr($type, 0, -1).' '.$remote_file.' uploaded to OP.'.NL;
if ($type == 'textures') {
	echo '<br><img src="http://mops.l3d.nl/bbcn/textures/'.$remote_file.'" style="max-width: 400; max-height: 300px; border: 1px solid #AAA;">';
}
die;

/*------------------------------------------------------------------------------
	processss...
------------------------------------------------------------------------------*/

/*
// get uploaded file path
$rules = array('req', 'max'=>2048);
$uploaded_file = upload::check($rules);

// get post data
$form = forms::check('upload');

// set remote vars (filename, filetype, world/objectpath)
$op_type = false;
if ($mime == 'image') {
	$op_type = 'texture';
}
elseif ($mime == '...') {
	$op_type = '...';
}
$path = '';

// store remote (ftp? ssh?)

// show result to user (incl. objectname or texture command for aw)

// show images directly
#TODO: create screenshot of objects as well
*/
?>