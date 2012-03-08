<?php
/*------------------------------------------------------------------------------
	output escaping
	
	use html_text($value) or html_attribute($value) to escape text before placing
		into html-attributes or anywhere as text (NOT in script or js/css variables)
	
	use url_argument(string/array) to escape all GET values after the domain
		url_argument('pages/about') or '?foo='.url_argument('bar')
------------------------------------------------------------------------------*/

class output {

/*------------------------------------------------------------------------------
	make things look better
------------------------------------------------------------------------------*/

public static function add_paragraphs($plain_text, $container=false) {
	// first add paragraphs and line breaks
	$paragraphed = str_replace(NL.NL, '</p>'.'<p>', $plain_text);
	$paragraphed = str_replace(NL, '<br>', $paragraphed);

	// add real newlines as well
	// but only after adding both html-tags, so they don't redo things
	$paragraphed = str_replace('</p>', '</p>'.NL, $paragraphed);
	$paragraphed = str_replace('<br>', '<br>'.NL, $paragraphed);

	if ($container) {
		$paragraphed = '<p>'.$paragraphed.'</p>';
	}
	return $paragraphed;
}

/*------------------------------------------------------------------------------
	escape for inclusion inside html
------------------------------------------------------------------------------*/

public static function html_text($value) {
	// htmlentities does a lot more, but doesn't protect more against XSS
	$value = htmlspecialchars($value, ENT_QUOTES, DEFAULT_CHARSET, $double_encode=false);
	
	// also change the slash
	// https://www.owasp.org/index.php/XSS_%28Cross_Site_Scripting%29_Prevention_Cheat_Sheet
	$value = str_replace('/', '&#x2F;', $value);
	
	return $value;
}

public static function html_attribute($value) {
	// go for the idea that we only have properly quotes attributes
	// then we only need to escape the quote (and all other things from htmlspecialchars)
	$value = self::html_text($value);
	
	// check for non-alphanumeric characters with an ASCII value lower then 256
	// replace them with their hexadecimal html entity
	// https://www.owasp.org/index.php/XSS_%28Cross_Site_Scripting%29_Prevention_Cheat_Sheet
	/*
	if (preg_match_all('/([^a-zA-Z0-9])/u', $value, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$char = $match[1];
			$ascii = ord($char);
			
			if ($ascii < 256) {
				$hex_ascii = dechex($ascii);
				$safe = '&#x'.$hex_ascii.';';
				$value = str_replace($char, $safe, $value);
			}
		}
		
	}
	*/
	
	return $value;
}

/*------------------------------------------------------------------------------
	escape arguments for urls
------------------------------------------------------------------------------*/

public static function url_argument($argument) {
	if (is_array($argument)) {
		foreach ($argument as &$single_arg) {
			$single_arg = output::url_argument($single_arg);
		}
	}
	else {
		$argument = rawurlencode($argument);
	}
	
	return $argument;
}

}
?>