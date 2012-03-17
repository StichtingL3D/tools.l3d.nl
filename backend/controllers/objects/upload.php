<?php
/*------------------------------------------------------------------------------
	upload: uploading files to an objectpath, not needing port 21
------------------------------------------------------------------------------*/

$session = load::model('session', $else='login');
$session->require_level('citizen+', $else='login');

/*
echo '$_GET:'; print_r($_GET);
echo '$_POST:'; print_r($_POST);
echo '$_FILES:'; print_r($_FILES);
echo '$_SERVER:'; print_r($_SERVER);

echo 'file_uploads: '.ini_get('file_uploads').NLd;
echo 'upload_max_filesize: '.ini_get('upload_max_filesize').NLd;
echo 'memory_limit: '.ini_get('memory_limit').NLd;
echo 'max_execution_time: '.ini_get('max_execution_time').NLd;
echo 'max_input_time: '.ini_get('max_input_time').NLd;
echo 'post_max_size: '.ini_get('post_max_size').NLd;
echo 'max_file_uploads: '.ini_get('max_file_uploads').NLd;
echo 'upload_tmp_dir: '.ini_get('upload_tmp_dir').NLd;
echo 'sys_get_temp_dir: '.sys_get_temp_dir().NLd;
echo 'open_basedir: '.ini_get('open_basedir').NLd;
echo 'is_readable: '.is_readable(sys_get_temp_dir()).NLd;
echo 'is_writable: '.is_writable(sys_get_temp_dir()).NLd;

$files = glob('/tmp/*');
print_r($files);
*/

if (ini_get('file_uploads') == false) {
	show_error('file_uploads disabled');
}

/*------------------------------------------------------------------------------
	show the upload form
------------------------------------------------------------------------------*/
function phpini_to_bytes($value) {
	// function from http://nl2.php.net/manual/en/function.ini-get.php
	$letter = strtolower($value[strlen($value)-1]);
	switch($letter) {
		case 'g': $value *= 1024;
		case 'm': $value *= 1024;
		case 'k': $value *= 1024;
	}
	return $value;
}

if (empty($_POST)) {
	$data = array(
		'upload_max_filesize' => phpini_to_bytes(ini_get('upload_max_filesize')),
	);
	
	page::title('Objecten');
	page::show('upload/form', $data);
	exit;
}

/*------------------------------------------------------------------------------
	TEMPORARY: quick 'n dirty
	
	TODO:
	- fill in the password
	- test local: https://help.ubuntu.com/10.04/serverguide/C/ftp-server.html
	- use ssh2_sftp() for sftp instead of ftp_ssl_connect()
------------------------------------------------------------------------------*/

// check
$allowed_types = array_flip(array('avatars','models','seqs','sounds','textures'));
$type = $_POST['type'];
if (isset($allowed_types[$type]) == false) {
	show_error('allowed_types => false');
}

$file = reset($_FILES);
if (is_uploaded_file($file['tmp_name']) == false) {
	show_error('is_uploaded_file => false');
}
if (is_readable($file['tmp_name']) == false) {
	show_error('is_readable => false');
}

// prepare
$config = load::config('upload');
$remote_path = $config['path'].$type.'/';
$remote_file = basename($file['name']);
if (preg_match('/[^a-z0-9._-]/', $remote_file)) {
	show_message('preg_match('.$remote_file.') => false', 'filenames can only contain a-z 0-9 .(dot) _(underscore) -(dash)');
}

$remote_connection = ftp_connect($config['host']); // use ssh2_sftp() for sftp
if ($remote_connection == false) {
	show_error('ftp_connect => false ('.$remote_connection.')');
}
$remote_login_check = ftp_login($remote_connection, $config['user'], base64_decode($config['pass']));
if ($remote_login_check == false) {
	show_error('ftp_login => false ('.$remote_login_check.')');
}
#ftp_pasv($remote_connection, true); // remedy for all kind of ftp errors, see php.net/function.ftp-put.php.html#90518 and more

$remote_chdir_check = ftp_chdir($remote_connection, $remote_path);
if ($remote_chdir_check == false) {
	show_error('ftp_chdir => false ('.$remote_chdir_check.')');
}
$remote_exists_check = ftp_size($remote_connection, $remote_file);
if ($remote_exists_check > 0) {
	show_message('ftp_size => false', 'file already exists');
}

// send
$remote_upload_check = ftp_put($remote_connection, $remote_file, $file['tmp_name'], FTP_BINARY);
if ($remote_upload_check == false) {
	show_error('ftp_put => false ('.$remote_upload_check.')');
}
$remote_close_check = ftp_close($remote_connection);
if ($remote_close_check == false) {
	show_error('ftp_close => false ('.$remote_close_check.')');
}

show_uploaded($type, $remote_file);

function show_error($message) {
	error::mail($message);
	$data = array(
		'upload_max_filesize' => phpini_to_bytes(ini_get('upload_max_filesize')),
		'error' => array(
			'message' => $message,
		),
	);
	
	page::title('Objecten');
	page::show('upload/form', $data);
	exit;
}

function show_message($debug, $message) {
	$data = array(
		'upload_max_filesize' => phpini_to_bytes(ini_get('upload_max_filesize')),
		'message' => $message,
		'debug' => $debug,
	);
	
	page::title('Objecten');
	page::show('upload/form', $data);
	exit;
}

function show_uploaded($type, $remote_file) {
	$data = array(
		'upload_max_filesize' => phpini_to_bytes(ini_get('upload_max_filesize')),
		'uploaded' => array(
			'type' => substr($type, 0, -1),
			'name' => $remote_file,
			'type_texture' => ($type == 'textures'),
		),
	);
	
	page::title('Objecten');
	page::show('upload/form', $data);
	exit;
}

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