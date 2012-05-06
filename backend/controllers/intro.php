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
	'contact',
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
if (!empty($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] < 0) {
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

$places = json_decode(PLACES, true);

?>
<!DOCTYPE html>
<html>
<head>
	<title>L3D tools</title>
	<link rel="shortcut icon" href="/favicon.ico">
	<link rel="stylesheet" type="text/css" media="all" href="<?php echo $places['www_frontend']; ?>css/intro/styles.css">
	<!--[if lte IE 7]>
	<link rel="stylesheet" type="text/css" media="all" href="<?php echo $places['www_frontend']; ?>css/intro/styles-ie.css">
	<![endif]-->
	<!--
	<script type="text/javascript" src="<?php echo $places['www_frontend']; ?>js/intro/jquery-1.5.min.js"></script>
	<script type="text/javascript" src="<?php echo $places['www_frontend']; ?>js/intro/scripts.js"></script>
	-->
</head>
<body>
	<div id="header">
		<h1><a href="<?php echo $places['www']; ?>">L3D tools</a></h1>
		<h2>tools voor virtuele omgevingen</h2>
	</div>
	<p id="intro">Hier ontwikkelt <a href="http://www.l3d.nl/">L3D</a> een serie tools voor gebruik in de <a href="http://www.l3d.nl/downloaden">L3Daw software</a>. Neem <a href="http://www.l3d.nl/contact">contact</a> op als je geïnteresseerd bent in het gebruik van deze tools.</p>
	<?php
	/*--- tempt user to next page ---*/
	if ($tempt_next) {
		$next_page = $page - 1;
		if ($next_page > 0) {
			$next_page *= -1;
		}
		$next_link = $places['www'].'intro?page='.$next_page;
		echo NL.'	<div id="right" class="temptation_next">';
		echo NL.'		<h3><a href="'.$next_link.'" title="verder in de tijd"><span>&raquo;</span> '.$tempt_next['title'].'</a></h3>';
		echo NL.'		<a class="thumb" href="'.$next_link.'"><img src="'.$places['www_frontend'].'img/intro/'.$tempt_next['thumb'].'" alt="screenshot van '.$tempt_next['title'].' (naar grote versie)"></a>';
		echo NL.'		<p>'.$tempt_next['text'].'</p>';
		echo NL.'		<p class="colophon">'.$tempt_next['colophon'].'</p>';
		#echo NL.'		<p class="link">&raquo; <a href="'.$next_link.'" title="naar '.$tempt_next['title'].'">naar de website</a></p>';
		echo NL.'	</div>';
	}
	else {
		echo NL.'	<div id="contact">';
		echo NL.'		<h3>Inspiratie gekregen?</h3>';
		echo NL.'		<p>Wil je gebruik maken van deze tools? Je kunt ze <span class="call">aanvragen</span> voor jouw project.</p>';
		echo NL.'		<p>Heb je inspiratie gekregen om <span class="call">zelf tools te maken</span>?</p>';
		echo NL.'		<p>Neem <a href="http://www.l3d.nl/contact">contact</a> met ons op!</p>';
		echo NL.'	</div>';
	}
	
	/*--- projects ---*/
	echo NL.'	<div id="projects">';
	foreach ($projects_shown as $i => $project) {
		if ($project == 'contact') {
			continue;
		}
		
		echo NL.'		<div>';
		echo NL.'			<h3>'.$project['title'].'</h3>';
		echo NL.'			<a class="thumb lightbox" href="#'./*$places['www_frontend'].'img/intro/'.$project['media'].'*/'"><img src="'.$places['www_frontend'].'img/intro/'.$project['thumb'].'" alt="screenshot van '.$project['title'].' (naar grote versie)"></a>';
		echo NL.'			<p>'.$project['text'].'</p>';
		echo NL.'			<p class="colophon">'.$project['colophon'].'</p>';
		#echo NL.'			<p class="link">&raquo; <a href="'.$project['link'].'" title="naar '.$project['title'].'">naar de website</a></p>';
		echo NL.'		</div>';
	}
	echo NL.'	</div>';
	
	/*--- tempt user to previous page ---*/
	if ($tempt_previous) {
		$previous_page = $page + 1;
		$previous_link = $places['www'].'intro?page=-'.$previous_page;
		echo NL.'	<div id="left" class="temptation_next">';
		echo NL.'		<h3><a href="'.$previous_link.'" title="terug in de tijd">'.$tempt_previous['title'].' <span>&laquo;</span></a></h3>';
		echo NL.'		<a class="thumb" href="'.$previous_link.'"><img src="'.$places['www_frontend'].'img/intro/'.$tempt_previous['thumb'].'" alt="screenshot van '.$tempt_previous['title'].' (naar grote versie)"></a>';
		echo NL.'		<p>'.$tempt_previous['text'].'</p>';
		echo NL.'		<p class="colophon">'.$tempt_previous['colophon'].'</p>';
		#echo NL.'		<p class="link">&raquo; <a href="'.$previous_link.'" title="naar '.$tempt_previous['title'].'">naar de website</a></p>';
		echo NL.'	</div>';
	}
	
	/*--- done ---*/
	echo NL;
	?>
	<ul id="development">
		<p>We zijn voortdurend met nieuwe tools bezig. Wil je meedenken? Neem dan contact op!</p>
		<li>
			<h4>Upload</h4>
			<p>Eenvoudig toevoegen van objecten</p>
		</li>
		<li>
			<h4>Admin</h4>
			<p>Wereldbeheer en inzicht in het gebruik</p>
		</li>
	</ul>
	<div id="footer">
		<ul id="menu">
			<li><a href="<?php echo $places['www']; ?>inloggen">inloggen</a></li>
			<li><a href="http://www.l3d.nl/contact">contact</a></li>
		</ul>
		<p>Stichting L3D, <?php echo date('Y'); ?></p>
	</div>
</body>
</html>
