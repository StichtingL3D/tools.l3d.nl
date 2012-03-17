<?php
/*------------------------------------------------------------------------------
	objects overview: showing the uploaded and default objects from a multi-op
------------------------------------------------------------------------------*/

$session = load::model('session', $else='login');
$session->require_level('citizen+', $else='login');

page::title('Objecten');
page::show('objects/overview');
