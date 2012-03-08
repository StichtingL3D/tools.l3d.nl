<?php
/*------------------------------------------------------------------------------
	adding timestamps to static resources
------------------------------------------------------------------------------*/

class resources {

private static $allowed_regex = 'a-z0-9\/_\.-';

public static function get_script_path($path) {
	$path = preg_replace('/[^'.self::$allowed_regex.']/', '', $path);
	
	if ($path == 'jquery') {
		$path = 'jquery/jquery-latest.min.js';
	}
	if (strpos($path, '.js') === false) {
		$path .= '.js';
	}
	
	return self::add_static_timestamp($path, 'js');
}

public static function get_style_path($path) {
	$path = preg_replace('/[^'.self::$allowed_regex.']/', '', $path);
	
	if (strpos($path, '.css') === false) {
		$path .= '.css';
	}
	
	return self::add_static_timestamp($path, 'css');
}

public static function get_image_path($path) {
	$path = preg_replace('/[^'.self::$allowed_regex.']/', '', $path);
	
	return self::add_static_timestamp($path, 'img');
}

private static function add_static_timestamp($path, $type) {
	// get full path and extension
	$ext = strrchr($path, '.');
	$places = json_decode(PLACES, true);
	$full_path = $places['frontend'].$type.'/'.$path;
	if (file_exists($full_path) == false) {
		throw new Exception('static resource "'.$path.'" not found');
	}
	
	// find out the latest modified date
	$timestamp = filemtime($full_path);
	
	// add timestamp before extension on the original path (without places)
	$timestamped_path = str_replace($ext, '.'.$timestamp.$ext, $path);
	return $timestamped_path;
}

}
?>