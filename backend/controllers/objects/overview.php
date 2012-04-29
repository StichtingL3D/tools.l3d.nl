<?php
/*------------------------------------------------------------------------------
	objects overview: showing the uploaded and default objects from a multi-op
------------------------------------------------------------------------------*/

$session = load::model('session', $else='login');
$session->require_level('citizen+', $else='login');

$data = array(
	'token' => forms::get_token('objects/upload'),
	'upload_max_filesize' => upload::get_max_filesize(),
);

if (isset($_GET['fout'])) {
	$data['upload_failed'] = true;
}
elseif (isset($_GET['klaar'])) {
	$object_id = input::validate($_GET['klaar'], 'id', $silent=true);
	$object = new object($object_id);
	
	$data['object']['filename'] = $object->filename;
	$data['object']['type'] = $object->type;
	$data['object']['type_'.$object->type] = true;
	$data['world']['name'] = 'BBCN';
	$data['new_object'] = true;
}

page::title('Objecten');
page::show('objects/overview', $data);
