<?php
/*------------------------------------------------------------------------------
	mustache - more advanced but elegant and light template system
	
	http://mustache.github.com/mustache.5.html
	http://mustache.github.com/mustache.1.html
	https://github.com/bobthecow/mustache.php
------------------------------------------------------------------------------*/

class mustache_tpl {

private static $partials;

public static function load_template($file, $ext='.html') {
	$places = json_decode(PLACES, true);
	$path = $places['backend'].'templates/'.$file.$ext;
	if (file_exists($path) == false) {
		throw new Exception('template not found, looked at templates/'.$file.$ext);
	}
	return file_get_contents($path);
}

/*------------------------------------------------------------------------------
	parsing templates
------------------------------------------------------------------------------*/
public static function parse($template_name, $data=false, $type='.html') {
	$template = self::load_template($template_name, $type);

	// auto un-escape emails
	if ($type == '.txt') {
		$template = '{{%UNESCAPED}}'.NL.$template;
	}
	
	// add common places and defines
	$template = self::replace_constants($template);
	
	// skip simple templates from going through mustache
	if (strpos($template, '{{') === false) {
		return $template;
	}
	
	// add the mustache to all faces ;-)
	$mustache = self::new_mustache();
	$rendered_template = $mustache->render($template, $data, self::$partials);
	
	return $rendered_template;
}

public static function parse_email($template_name, $data=false) {
	return self::parse($template_name, $data, '.txt');
}

/*------------------------------------------------------------------------------
	helping the template rendering
------------------------------------------------------------------------------*/
public static function replace_constants($template) {
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

/*------------------------------------------------------------------------------
	starting up mustache
------------------------------------------------------------------------------*/
private static function new_mustache() {
	$options = array(
		// enforce our own charset over their default
		'charset' => DEFAULT_CHARSET,
		
		// use smarty-alike delimiters
		#'delimiters' => '{$ }',
	);
	
	return new _Mustache(null, null, null, $options);
}

public static function construct() {
	$places = json_decode(PLACES, true);
	$mustache_path = $places['backend'].'includes/Mustache/Mustache.php';
	
	$result = require_once($mustache_path);
	if ($result == false) {
		throw new Exception('failed to load mustache template system');
	}
}

/*------------------------------------------------------------------------------
	end of the mustache_tpl class, construct the main helper
------------------------------------------------------------------------------*/
}
mustache_tpl::construct();

/*------------------------------------------------------------------------------
	changes to the mustache default class
------------------------------------------------------------------------------*/
class _Mustache extends Mustache {
	// scream a bit louder
	protected $_throwsExceptions = array(
		// defaults which are fine
		MustacheException::UNKNOWN_VARIABLE         => false,
		MustacheException::UNCLOSED_SECTION         => true,
		MustacheException::UNEXPECTED_CLOSE_SECTION => true,
		MustacheException::UNKNOWN_PRAGMA           => true,
		
		// changed from the default
		MustacheException::UNKNOWN_PARTIAL          => true, // not found sub-templates
	);
	
	// enforce our own charset over their default
	protected $_charset = DEFAULT_CHARSET;
	
	// better protection against XSS, use our own output instead:
	// htmlspecialchars (instead of htmlentities) and also replace the slash
	protected function _renderEscaped($tag_name, $leading, $trailing) {
		// catch url arguments
		if (strpos($tag_name, '?') === 0) {
			$tag_name = trim(substr($tag_name, 1));
			$escaped = output::url_argument($this->_getVariable($tag_name));
		}
		// and all other arguments
		else {
			$escaped = output::html_text($this->_getVariable($tag_name));
		}
		
		return $leading . $escaped . $trailing;
	}
	
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
		$template = mustache_tpl::load_template($type);
		$template = mustache_tpl::replace_constants($template);
		$template = str_replace($search, $replace, $template);
		return $template;
	}
	
	// dynamicly load partials (subtemplates)
	// so we don't have to give them as arguments to the parser
	protected function _getPartial($tag_name) {
		$places = json_decode(PLACES, true);
		$template_path = $places['backend'].'templates/'.$tag_name.'.html';
		
		if (file_exists($template_path)) {
			$template = mustache_tpl::load_template($tag_name);
			
			// add common places and defines
			$template = mustache_tpl::replace_constants($template);
			
			// add to the stack
			$this->_partials[$tag_name] = $template;
		}
		
		return parent::_getPartial($tag_name);
	}
}
?>