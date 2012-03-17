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
		'objecten'        => 'objects',
		'tentoonstelling' => 'gallery',
		'3dwiki'          => '3dwiki',
		'telegrammen'     => 'telegrams',
		'statistieken'    => 'statistics',
		'status'          => 'status',
		'inloggen'        => 'login',
		'instellingen'    => 'settings',
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
			$data['user']['level'] = $user->level;
			$data['user']['is_'.$user->level] = true;
			$data['user']['is_citizen_or_higher'] = $user->is_citizen_or_higher();
			$data['user']['is_worldct_or_higher'] = $user->is_worldct_or_higher();
			$data['user']['is_l3dmember_or_higher'] = $user->is_l3dmember_or_higher();
			$data['user']['is_universect_or_higher'] = $user->is_universect_or_higher();
			
			// extra levels
			if ($user->level == 'universect' || $user->level == 'webmaster') {
				$data['user']['has_admin_access'] = true;
			}
		}
	}
	catch (Exception $e) {
		// continue without user variables
	}
}

}
?>