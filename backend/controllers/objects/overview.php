<?php
/*------------------------------------------------------------------------------
	objects overview: showing the uploaded and default objects from a multi-op
	
	TODO:
	- show modifying actions based on mime
	- show current uploaded objects, incl. uploaders name
		- show my objects
		- show recent uploads
	- show objects from base objectpaths
	- previews of sound files
	- rename files when existing
	- tip to use actions
	- more ... see trello
------------------------------------------------------------------------------*/

$session = load::model('session', $else='login');
$session->require_level('citizen+', $else='login');

$data = array(
	'upload_token' => forms::get_token('objects/upload'),
	'change_token' => forms::get_token('objects/change'),
	'upload_max_filesize' => upload::get_max_filesize(),
);

if (isset($_GET['fout'])) {
	$data['upload_failed'] = true;
}
elseif (isset($_GET['klaar'])) {
	$data['new_object'] = true;
	
	$object_id = input::validate($_GET['klaar'], 'id', $silent=true);
}
elseif (isset($_GET['verplaatst'])) {
	$data['moved_object'] = true;
	
	$object_id = input::validate($_GET['verplaatst'], 'id', $silent=true);
}

if (isset($object_id)) {
	try {
		$object = new object($object_id);
		
		$data['object']['id'] = $object->id;
		$data['object']['filename'] = $object->filename;
		$data['object']['type'] = $object->type;
		$data['object']['type_'.$object->type] = true;
		$data['object']['pretty_type'] = $object->pretty_type;
		$data['world']['name'] = 'BBCN';
	}
	catch (Exception $e) {
		$data['object_error'] = true;
	}
}

page::title('Objecten');
page::show('objects/overview', $data);
