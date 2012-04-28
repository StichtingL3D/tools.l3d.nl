<?php
/*------------------------------------------------------------------------------
	mustache - more advanced but elegant and light template system
	
	http://mustache.github.com/mustache.5.html
	http://mustache.github.com/mustache.1.html
	https://github.com/bobthecow/mustache.php
------------------------------------------------------------------------------*/

class mustache_tpl {

/*------------------------------------------------------------------------------
	parsing templates for web and mail
------------------------------------------------------------------------------*/
public static function parse($template_name, $data=false, $type='.html') {
	$m_templates = new MustacheTemplates();
	$m_templates->extension = $type;
	$template = $m_templates[$template_name];
	
	// skip simple templates from going through mustache
	if (strpos($template, '{{') === false) {
		return $template;
	}
	
	// auto un-escape emails
	if ($type == '.txt') {
		$template = '{{%UNESCAPED}}'.NL.$template;
	}
	
	// add the mustache to all faces ;-{)
	$mustache = self::new_mustache();
	$rendered_template = $mustache->render($template, $data, $m_templates);
	
	return $rendered_template;
}

public static function parse_email($template_name, $data=false) {
	return self::parse($template_name, $data, '.txt');
}

/*------------------------------------------------------------------------------
	escaping
	
	better protection against XSS using our own output helper
	htmlspecialchars (instead of htmlentities) and also replace the slash
------------------------------------------------------------------------------*/
public static function escape($value) {
	// catch url arguments
	if (strpos($value, '?') === 0) {
		$value = trim(substr($value, 1));
		$escaped = output::url_argument($value);
	}
	// and all other arguments
	else {
		$escaped = output::html_text($value);
	}
	
	return $escaped;
}

/*------------------------------------------------------------------------------
	starting up mustache
------------------------------------------------------------------------------*/
private static function new_mustache() {
	$options = array(
		// use our own escaping from the output helper
		'escape' => array('mustache_tpl', 'escape'),
		
		// enforce our own charset over their default
		'charset' => DEFAULT_CHARSET,
		
		// scream a bit louder
		'throws_exceptions' => array(
			MustacheException::UNKNOWN_PARTIAL => true, // not found sub-templates
		),
	);
	
	return new _Mustache(null, null, null, $options);
}

public static function construct() {
	$places = json_decode(PLACES, true);
	$mustache_path = $places['backend'].'includes/Mustache/Mustache.php';
	
	try {
		require_once($mustache_path);
	}
	catch (Exception $e) {
		throw new Exception('failed to load mustache template system');
	}
}

}

// load directly, our class extending Mustache needs it
mustache_tpl::construct();

/*------------------------------------------------------------------------------
	template/partial loading
------------------------------------------------------------------------------*/
class MustacheTemplates implements ArrayAccess {

public $extension = '.html';
private $cache;
private $base_path;

public function __construct() {
	$places = json_decode(PLACES, true);
	$this->base_path = $places['backend'].'templates/';
}

private function load($template_file) {
	$path = $this->base_path.$template_file;
	if (file_exists($path) == false) {
		throw new Exception('template not found, looked at '.$template_file);
	}
	
	$template_content = file_get_contents($path);
	
	// add common places and defines
	$template_content = $this->replace_constants($template_content);
	
	return $template_content;
}

private function replace_constants($template) {
	$places = json_decode(PLACES, true);
	
	$search = array(
		'{{public}}',
		'{{frontend}}',
		'{{charset}}',
		'{{app_domain}}',
		'{{app_name}}',
	);
	$replace = array(
		$places['www'],
		$places['www_frontend'],
		DEFAULT_CHARSET,
		APP_DOMAIN,
		APP_NAME,
	);
	
	return str_replace($search, $replace, $template);
}

public function offsetExists($template_name) {
	if (isset($this->cache[$template_name])) {
		return true;
	}
	
	$path = $this->base_path.$template_name.$this->extension;
	if (file_exists($path)) {
		return true;
	}
	
	return false;
}

public function offsetGet($template_name) {
	if (isset($this->cache[$template_name]) == false) {
		$this->cache[$template_name] = $this->load($template_name.$this->extension);
	}
	
	return $this->cache[$template_name];
}

public function offsetSet($template_name, $new_value) {
	throw new Exception('can not write templates');
}

public function offsetUnset($template_name) {
	throw new Exception('can not write templates');
}

}

/*------------------------------------------------------------------------------
	changes to the mustache default class
------------------------------------------------------------------------------*/
class _Mustache extends Mustache {
	
	// adding styles and scripts via a virtual partial
	protected function _renderPartial($tag_name, $leading, $trailing) {
		// handle normal partials directly
		if (strpos($tag_name, ' ') === false) {
			return parent::_renderPartial($tag_name, $leading, $trailing);
		}
		
		// extract variables from the partial-call
		$tag_name_args = explode(' ', $tag_name);
		$type = $tag_name_args[0];
		if ($type == 'style') {
			$search = array('{{media}}', '{{path}}');
			$replace = array(
				(isset($tag_name_args[2])) ? $tag_name_args[2] : 'screen',
				resources::get_style_path($tag_name_args[1])
			);
		}
		elseif ($type == 'script') {
			$search = array('{{path}}', '{{async}}');
			$replace = array(
				resources::get_script_path($tag_name_args[1]),
				(isset($tag_name_args[2]) && $tag_name_args[2] == 'async') ? ' async="true"' : false
			);
		}
		elseif ($type == 'meta') {
			$search = array('{{name}}', '{{content}}');
			$replace = array($tag_name_args[1], $tag_name_args[2]);
		}
		else {
			throw new Exception('invalid partial "'.$tag_name.'"');
		}
		
		// render the template directly
		// as the template is simple, and Mustache's context process too complex
		$m_templates = new MustacheTemplates();
		$template = $m_templates[$type];
		$template = str_replace($search, $replace, $template);
		return $template;
	}
	
}
?>