<?php
/*------------------------------------------------------------------------------
	page - shows pages and adds a layer over the template engine

	usage:
		show($template_name, $data)
		this parses the template and echo's it directly
	
	optionally use before show():
		title($page_title)
		description($head_description)
------------------------------------------------------------------------------*/

class page {

private static $description = false;
private static $keywords = false;
private static $group = false;

private static $title = false;
private static $subtitle = false;
private static $head_title_differs = false;

private static $old_styles = false;

/*------------------------------------------------------------------------------
	show the page
------------------------------------------------------------------------------*/
public static function show($template_name, $data=false) {
	if (empty($data)) {
		$data = array();
	}

	// add meta data and page titles
	self::add_titles($data);
	self::add_meta_data($data);
	
	// add user data
	self::add_user_data($data);
	
	// render and show
	$data['use_old_styles'] = (self::$old_styles) ? true : false;
	$rendered_page = mustache_tpl::parse($template_name, $data);
	echo $rendered_page;
}

/*------------------------------------------------------------------------------
	add meta data
------------------------------------------------------------------------------*/
public static function title($title, $subtitle=false) {
	self::$title = $title;
	
	if ($subtitle) {
		self::$subtitle = $subtitle;
	}
}
public static function subtitle($subtitle) {
	self::$subtitle = $subtitle;
}
public static function head_title($title) {
	self::$head_title_differs = $title;
}

public static function description($head_description) {
	self::$description = $head_description;
}
public static function keywords($head_keywords) {
	self::$keywords = $head_keywords;
}

public static function group_on($type, $value) {
	$group_name = str_replace($value, $type, REQUEST);
	self::$group = $group_name;
}
public static function group_raw($group_name) {
	self::$group = $group_name;
}

public static function use_old_styles() {
	self::$old_styles = true;
}

/*------------------------------------------------------------------------------
	internal methods
------------------------------------------------------------------------------*/
private static function add_meta_data(&$data) {
	if (self::$title) {
		$data['head_title'] = self::$title;
		if (self::$head_title_differs) {
			$data['head_title'] = self::$head_title_differs;
		}
	}
	if (empty($data['head_title']) && empty($data['page_title']) == false) {
		$data['head_title'] = $data['page_title'];
	}
	
	if (self::$description) {
		$data['page_description'] = self::$description;
	}
	if (self::$keywords) {
		$data['page_keywords'] = self::$keywords;
	}
	
	// group pages for analytic purposes
	if (self::$group) {
		$data['page_group'] = self::$group;
		if (strpos($data['page_group'], '/') !== 0) {
			$data['page_group'] = '/'.$data['page_group'];
		}
	}
}

private static function add_titles(&$data) {
	$config = load::config('meta', 'pages');
	
	if (self::$title) {
		$data['page_title'] = self::$title;
	}
	elseif (isset($config[REQUEST]['title'])) {
		$data['page_title'] = $config[REQUEST]['title'];
	}
	
	if (self::$subtitle) {
		$data['page_subtitle'] = self::$subtitle;
	}
	elseif (isset($config[REQUEST]['subtitle'])) {
		$data['page_subtitle'] = $config[REQUEST]['subtitle'];
	}
}

private static function add_user_data(&$data) {
	$data['user'] = array(
		'is_loggedin' => false,
	);
	
	// make the active menu item
	$active_items = array(
		'portretfoto'   => 'search',
		'fotograaf'     => 'search',
		'locaties'      => 'locations',
		'missie'        => 'mission',
		'team'          => 'team',
		'wat_is_het' 	=> 'what',
		'valentijn' 	=> 'valentijn',
		'dashboard'     => 'dashboard',
		'beheer'        => 'admin',
		'inloggen'      => 'login',
		'info_voor_fotografen'    => 'photographers_info',
		'aanmelden_als_fotograaf' => 'photographers_info',
	);
	$request_basis = REQUEST;
	if (strpos(REQUEST, '/') || strpos(REQUEST, '?')) {
		$request_basis_endpoint = (strpos(REQUEST, '/')) ? strpos(REQUEST, '/') : strpos(REQUEST, '?');
		$request_basis = substr(REQUEST, 0, $request_basis_endpoint);
	}
	if (isset($active_items[$request_basis])) {
		$data['active'][$active_items[$request_basis]] = true;
	}
	
	try {
		$session = load::model('session');
		$data['session'] = $session->get_data();
		
		// add user variables to the data
		if ($session->user_id) {
			$user = load::model('user', $session->user_id);

			$data['user']['is_loggedin'] = true;
			$data['user']['id'] = $session->user_id;
			$data['user']['full_name'] = $user->full_name;
			$data['user']['emailaddress'] = $user->emailaddress;
			$data['user']['level'] = $user->level;
			$data['user']['is_client'] = $user->is_client();
			$data['user']['is_photographer'] = $user->is_photographer();
			$data['user']['is_photographer_or_higher'] = $user->is_photographer_or_higher();
			$data['user']['is_ambassador'] = $user->is_ambassador();
			$data['user']['is_ambassador_or_higher'] = $user->is_ambassador_or_higher();
			$data['user']['is_admin'] = $user->is_admin();
			$data['user']['is_admin_or_higher'] = $user->is_admin_or_higher();
			$data['user']['is_webmaster'] = $user->is_webmaster();
			
			// extra levels
			if ($user->level == 'ambassador' || $user->level == 'admin' || $user->level == 'webmaster') {
				$data['user']['has_admin_access'] = true;
			}
			
			// add more as photographer
			$photographers = load::model('photographers');
			$pg = $photographers->find_by_userid($session->user_id, $extended=true);
			if ($pg) {
				$data['user']['has_active_profile'] = $pg['profile_active'];
				$data['user']['photographer_id'] = $pg['id'];
				$data['user']['photographer_photo'] = $pg['photo'];
				$data['user']['photographer_vanity'] = $pg['profile_vanity'];
			}
		}
	}
	catch (Exception $e) {
		// continue without user variables
	}
	
	// add active profile for owning photographer
	if (isset($data['user']['photographer_vanity']) && strpos(REQUEST, 'fotograaf/'.$data['user']['photographer_vanity']) === 0) {
		$data['active']['search'] = false;
		$data['active']['profile'] = true;
	}
	
	// have a look for a booking
	try {
		$booking_session = load::model('session', $else=false, $id=false, 'booking');
		if ($booking_session->get('confirmed')) {
			$data['booking']['done'] = true;
		}
		else {
			$data['booking']['preparing'] = true;
			
			if (strpos(REQUEST, 'boeken/') === false && strpos(REQUEST, 'beschikbaarheid/') === false) {
				$data['booking']['distracted'] = true;
			}
		}
	}
	catch (Exception $e) {
		// continue without booking data
	}
}

}
?>