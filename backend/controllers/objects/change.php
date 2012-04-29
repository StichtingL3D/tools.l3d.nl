<?php
/*------------------------------------------------------------------------------
	change files in objectpaths
------------------------------------------------------------------------------*/

$session = load::model('session', $else='login');
$session->require_level('citizen+', $else='login');

function show_error($error_type, $exception=false) {
	echo $error_type.': <pre>'; print_r($exception); echo '</pre>'.NLd; die;
	error::mail($exception, $error_type);
	load::redirect('objecten?fout='.$error_type);
	exit;
}

/*------------------------------------------------------------------------------
	change type - move the file
------------------------------------------------------------------------------*/

$arguments = json_decode(ARGUMENTS, true);
$object_id = input::validate($arguments['id'], 'id', $silent=true);

$rules = array('type'=>array('string'));
$form = forms::check('objects/change', $rules);
$allowed_types = array_flip(array('avatars','models','seqs','sounds','textures'));
if (isset($allowed_types[$form['type']]) == false) {
	show_error('type', false, false);
	exit;
}

/*------------------------------------------------------------------------------
	move to objectpath
------------------------------------------------------------------------------*/

try {
	$object = new object($object_id);
}
catch (Exception $e) {
	show_error('object', $e);
	exit;
}

try {
	$objectpath = new objectpath('bbcn');
	$objectpath->change_type($object->filename, $object->type, $form['type']);
}
catch (Exception $e) {
	show_error('ftp', $e);
	exit;
}

try {
	$object->type = $form['type'];
}
catch (Exception $e) {
	show_error('object', $e);
	exit;
}

/*------------------------------------------------------------------------------
	change object
------------------------------------------------------------------------------*/

load::redirect('objecten?verplaatst='.$object_id);
