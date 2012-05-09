<?php

try {
	$session = load::model('session');
	$user = load::model('user', $session->user_id);
	$user->goto_home();
}
catch (Exception $e) {
	// not logged in, stay here
}

/*------------------------------------------------------------------------------
	content
------------------------------------------------------------------------------*/
$projects_per_page = 3;
$projects = array(
	array(
		'contact' => true,
	),
	array(
		'title' => 'Telegrammen',
		'thumb' => 'telegram_thumb.png',
		'media' => 'telegram.png',
		'link' => 'http://tools.l3d.nl/telegram',
		'date' => 1298224800,
		'text' => 'E-mail meldingen als je een telegram ontvangt',
		'colophon' => '',
	),
	array(
		'title' => 'Status',
		'thumb' => 'status_thumb.png',
		'media' => 'status.png',
		'link' => 'http://tools.l3d.nl/status/',
		'date' => 1296928800,
		'text' => 'Overzicht van de status van alle L3D servers',
		'colophon' => '',
	),
	array(
		'title' => 'Zoeken',
		'thumb' => 'search_thumb.png',
		'media' => 'search.png',
		'link' => 'http://tools.l3d.nl/search',
		'date' => 1292436000,
		'text' => 'Vind hulp in de 3Dwiki en werelden van andere projecten',
		'colophon' => '',
	),
	array(
		'title' => 'Teleporteren',
		'thumb' => 'teleport_thumb.png',
		'media' => 'teleport.png',
		'link' => 'http://tools.l3d.nl/teleport',
		'date' => 1292436000,
		'text' => 'Eenvoudige links om direct naar een wereld te teleporteren',
		'colophon' => '',
	),
	array(
		'title' => 'Acties',
		'thumb' => 'acties_thumb.png',
		'media' => 'acties.png',
		'link' => 'http://tools.l3d.nl/actietest',
		'date' => 1275238800,
		'text' => 'Stap-voor-stap zelf een actie maken voor op een object',
		'colophon' => '',
	),
	array(
		'title' => 'Tentoonstelling',
		'thumb' => 'gallery_thumb.png',
		'media' => 'gallery.png',
		'link' => 'http://tools.l3d.nl/wishfish',
		'date' => 1270141200,
		'text' => 'Upload afbeeldingen en video\'s voor een virtuele tentoonstelling',
		'colophon' => '',
	),
);

/*------------------------------------------------------------------------------
	hiding extra text
------------------------------------------------------------------------------*/
$more_id = false;
function more($type) {
	global $more_id;
	if ($type == 'start') {
		if ($more_id == false) {
			$more_id = uniqid();
		}
		$html = '<span id="more_'.$more_id.'" class="more">... (<a href="#show-more-text">meer</a>)</span> <span id="all_'.$more_id.'" class="all">';
	}
	if ($type == 'reopen') {
		if ($more_id == false) {
			$more_id = uniqid();
		}
		$html = '<span id="all2_'.$more_id.'" class="all">';
	}
	if ($type == 'close') {
		$html = '</span>';
	}
	if ($type == 'end') {
		$html = ' <br><span id="less_'.$more_id.'" class="less">(<a href="#show-less-text">minder</a>)</span></span>';
	}
	if ($type == 'reset') {
		$more_id = false;
	}
	return $html;
}

/*------------------------------------------------------------------------------
	pagination
------------------------------------------------------------------------------*/
$page = 0;
$page_max = floor( count($projects) / $projects_per_page );
if (!empty($_GET['page']) && is_numeric($_GET['page']) && (int)$_GET['page'] < 0) {
	$page = (int)$_GET['page'] * -1;
	if ($page > $page_max) {
		$page = $page_max;
	}
}

$projects_shown = array_slice($projects, $page * $projects_per_page, $projects_per_page);

$tempt_previous = false;
$tempt_next = false;
if ($page < $page_max) {
	$tempt_previous = $projects[ $projects_per_page + $page * $projects_per_page ];
}
if ($page > 0) {
	$tempt_next = $projects[ $page * $projects_per_page - 1 ];
}

/*------------------------------------------------------------------------------
	template
------------------------------------------------------------------------------*/

$data = array(
	'tempt_next' => $tempt_next,
	'tempt_prev' => $tempt_previous,
	'projects' => $projects_shown,
);

if ($tempt_next) {
	$next_page = $page - 1;
	if ($next_page > 0) {
		$next_page *= -1;
	}
	$data['next_page_id'] = $next_page;
}

if ($tempt_previous) {
	$previous_page = $page + 1;
	$data['prev_page_id'] = $previous_page;
}

page::title('tools voor virtuele omgevingen');
page::show('intro/intro', $data);
