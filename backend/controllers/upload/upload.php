<?php
/*------------------------------------------------------------------------------
	upload: uploading files to an objectpath, not needing port 21
------------------------------------------------------------------------------*/

$max_upload_filesize = 0;

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
	processss...
------------------------------------------------------------------------------*/

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
?>