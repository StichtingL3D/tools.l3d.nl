<?php
/*------------------------------------------------------------------------------
	input validation
	makes sure that input is secure, correct enconded, and validated
	
	input::validate($data, $rules)
		$data:	is the input data --- for example $_COOKIE
		$rules:	rules for validation of the data, and array with
			keys which correspond to the keys in the data-array,
			and values which is an array with different validation rules
		strings: $data can also be a string, $rules then is a single dimension array
	
	returns the requested data, or throws an InputException or ValidationException
	
	validation rules:
		req|required
		min|minimum_length =>
		max|maximum_length =>
		nonl|no_newlines
		int|integer, string|varchar, bool|boolean, arr|array
		email|emailaddress, slug, id, multiple_id(=>), hash, tags, url(=>),
			file|filename, name, city, postal|postcalcode, phone|phonenumber,
			gender, checkbox(=>)
		planned: float, ..
	
	rules which are not specified, should manually be checked afterwards
		for example: not adding 'req', means the controller has to check that itself
	
	for $_POST, use:
		$data = forms::check($controller, $rules)
	
	#TODO:
	- untested with $_POST arrays and $_POST checkboxes
------------------------------------------------------------------------------*/

class input {

private static $max_length_cap = 8192;
private static $regex_utf8_alpha = '\p{L}'; // the \p{L} is any (unicode) letter

/*------------------------------------------------------------------------------
	main callable methods
------------------------------------------------------------------------------*/

public static function validate($data, $rules, $silent=false) {
	$errors = false;
	$data_as_string = false;
	$new_data = array();
	
	// convert everything to an array
	if (is_array($data) == false) {
		$data_as_string = true;
		// rules is actually an array of rulesets in itself, wrap it
		if (is_array($rules) == false) {
			$rules = array($rules);
		}
		
		$data = array('single' => $data);
		$rules = array('single' => $rules);
	}
	
	foreach ($rules as $key => $single_ruleset) {
		// switch rules without arguments
		$single_ruleset = self::convert_ruleset($single_ruleset);
		
		try {
			// handle non-existing data
			if (empty($data[$key]) && (isset($single_ruleset['required']) || isset($single_ruleset['req']))) {
				throw new ValidationException('required');
			}
			elseif (empty($data[$key])) {
				continue;
			}
			$single_data = $data[$key];
			
			// validate
			if (isset($single_ruleset['array']) && is_array($single_data)) {
				unset($single_ruleset['array']);
				array_walk($single_data, 'self::secure_single');
				array_walk($single_data, 'self::validate_single', $single_ruleset);
			}
			else {
				$single_data = self::secure_single($single_data);
				$single_data = self::validate_single($single_data, $data_key=false, $single_ruleset);
			}
			$new_data[$key] = $single_data;
		}
		
		// store errors
		catch (InputException $e) {
			$errors[$key] = $e->getMessage();
		}
		catch (ValidationException $e) {
			$errors[$key] = $e->getMessage();
		}
	}
	
	// return the error
	if ($errors && $silent) {
		return false;
	}
	elseif ($errors) {
		$e = new ValidationException('see $exception->errors[]');
		$e->errors = $errors;
		throw $e;
	}
	
	// or return the data
	if ($data_as_string) {
		return reset($new_data);
	}
	else {
		return $new_data;
	}
}

// use this method if you don't want to validate the data (with this helper)
// convert html-entities, check encoding, cap on max chars, and correct newlines
public static function secure($data, $silent=false) {
	$errors = false;
	$data_as_string = false;
	
	// convert everything to an array
	if (is_array($data) == false) {
		$data_as_string = true;
		$data = array('single' => $data);
	}
	
	foreach ($data as $key => &$value) {
		try {
			if (is_array($value)) {
				array_walk($value, 'self::secure_single');
			}
			else {
				$value = self::secure_single($value);
			}
		}
		catch (InputException $e) {
			$errors[$key] = $e->getMessage();
		}
	}
	
	// return the error
	if ($errors && $silent) {
		return false;
	}
	elseif ($errors) {
		$e = new InputException('see $exception->errors[]');
		$e->errors = $errors;
		throw $e;
	}
	
	// or return the data
	if ($data_as_string) {
		return reset($data);
	}
	else {
		return $data;
	}
}

public static function url_argument($argument) {
	return rawurldecode($argument);
}

/*------------------------------------------------------------------------------
	internal methods
------------------------------------------------------------------------------*/

// check if things come in, in a correct way
private static function secure_single($data) {
	// check for valid utf-8, if not --- throws exception
	self::check_utf8($data);
	
	// convert html-entities --- don't complain, use the new data
	$data = self::make_real($data);
	
	// put a cap on the amount of chars --- throws exception
	self::max_length_cap($data);
	
	// correct newlines to unix standard --- don't complain, use the new data
	$data = self::correct_newlines($data);
	
	return $data;
}

private static function validate_single($data, $key=null, $rules) {
	foreach ($rules as $rule => $argument) {
		
		$validating_function = 'self::is_'.$rule;
		if (is_callable($validating_function) == false) {
			throw new Exception('unknown rule '.$rule);
		}
		
		// test this rule --- throws exception on errors
		#TODO: may change the data in simple ways?
		#$data = 
		#TODO: older php versions need an array callback for call_user_func
		$validating_function_array_callback = array('self', 'is_'.$rule);
		call_user_func($validating_function_array_callback, $data, $argument);
	}
	
	// strong type hinting
	if (ctype_digit($data) && $data[0] !== '0') {
		return (int)$data;
	}
	else {
		return trim($data);
	}
}

// copied to upload helper
private static function convert_ruleset($ruleset) {
	// make sure one ruleset can cope with rules with and without arguments
	/*
		$ruleset = array(
			0 => 'required',
			'min' => '25',
		)
		
		should become
		
		$ruleset = array(
			'required' => true,
			'min' => '25',
		)
	*/
	
	foreach ($ruleset as $key => $value) {
		if (is_int($key)) {
			$ruleset[$value] = true;
			unset($ruleset[$key]);
		}
	}
	
	return $ruleset;
}

/*------------------------------------------------------------------------------
	sub methods for secure()
------------------------------------------------------------------------------*/

private static function check_utf8($data) {
	// empty strings will throw a false positive
	if (strlen($data) == false) {
		return;
	}
	
	// a preg_match with the u(tf-8) modifier fails on invalid utf-8 or 5/6-sequence non-unicode
	// see test cases at: http://www.php.net/manual/en/reference.pcre.pattern.modifiers.php#54805
	$test = (bool)preg_match('/^.{1}/us', $data);
	if ($test == false) {
		throw new InputException('utf8');
	}
	
	// don't return
}

private static function make_real($data) {
	$data = html_entity_decode($data, ENT_QUOTES, DEFAULT_CHARSET);
	
	return $data;
}

private static function max_length_cap($data) {
	if (mb_strlen($data) > self::$max_length_cap) {
		throw new InputException('max_length_cap');
	}
	
	// don't return
}

private static function correct_newlines($data) {
	$data = str_replace("\r\n", "\n", $data);
	$data = str_replace("\r", "\n", $data);
	
	return $data;
}

/*------------------------------------------------------------------------------
	actual validators
	
	basics:
		req|required
		min|minimum_length => #
		max|maximum_length => #
		nonl|no_newlines
	
	variable types:
		int|integer
		str|string
		bool|boolean
		arr|array
		
	types:
		email|emailaddress
		slug
		id
		multiple_ids (=> # exact amount)
		hash
		tags
		url (=> relative|absolute)
		file|filename
		name
		city
		postal|postcalcode
		phone|phonenumber
		gender
		checkbox (=> value if not 'on')
		
	planned:
		float
------------------------------------------------------------------------------*/

/*------------------------------------------------------------------------------
	not yet implemented from old input::strip():
	
	// adding special chars to the a-z range
	// how-to 1: aeiouy + ' ` " ^ * ~
	// how-to 2: ,a ae ,c -d ~n 'n /o oe 's 'z .z ss
	// helps for Dutch, German, Swedish, Polish, Spanish, ..
	$a_to_z  = 'a-záàäâãåąæçćđéèëêẽíìïîĩñńóòöôõøœśúùüûũůýỳÿŷỹźż';
	$a_to_z .= 'A-ZÁÀÄÂÃÅĄÆÇĆĐÉÈËÊẼÍÌÏÎĨÑŃÓÒÖÔÕØŒŚÚÙÜÛŨŮÝỲŸŶỸŹŻ';
	$a_to_z .= 'ß';
	
	// adding special chars for editorial text
	// quotes: lsquo rsquo sbquo ldquo rdquo bdquo
	// stripes and circles: ndash mdash bull sdot hellip
	$editorial  = '‘’‚“”„';
	$editorial .= '–—•⋅…';
	
	alpha		$a_to_z
	alpha+	+  (space) -(dash)
	file		a-zA-Z0-9. -
	file+		+ /
	text		$a_to_z.'0-9.,:!?&() -
	text+		+ $editorial _;~@#%*+='"/
------------------------------------------------------------------------------*/

/*------------------------------------------------------------------------------
	validators: basics
------------------------------------------------------------------------------*/

// probably never used, already handled directly by input::validate()
private static function is_required($data) {
	if (empty($data)) {
		throw new ValidationException('required');
	}
}

private static function is_minimum_length($data, $minimal_length) {
	if (mb_strlen($data) < $minimal_length) {
		throw new ValidationException('minimum_length');
	}
}

private static function is_maximum_length($data, $maximum_length) {
	if (mb_strlen($data) > $maximum_length) {
		throw new ValidationException('maximum_length');
	}
}

private static function is_no_newlines($data) {
	if (strpos($data, "\n")) {
		throw new ValidationException('no_newlines');
	}
}

/*------------------------------------------------------------------------------
	validators: variable type
------------------------------------------------------------------------------*/

private static function is_integer($data) {
	if (is_int($data) == false) {
		throw new ValidationException('integer');
	}
}

private static function is_string($data) {
	if (is_string($data) == false) {
		throw new ValidationException('string');
	}
}

private static function is_boolean($data) {
	if (is_bool($data) == false) {
		throw new ValidationException('boolean');
	}
}

// never used as this is a way to pass multiple connected radio buttons
private static function is_array($data) {
	if (is_array($data) == false) {
		throw new ValidationException('array');
	}
}

/*------------------------------------------------------------------------------
	validators: advanced types
------------------------------------------------------------------------------*/

/*--- emailaddress ---*/
private static function is_emailaddress($data) {
	// first try to use Swift
	try {
		load::helper('email');
		email::check_emailaddress($data);
		return;
	}
	catch (Exception $e) {
		if ($e->getMessage() == 'invalid') {
			throw new ValidationException('emailaddress', 0);
		}
		
		// on other errors, continue with our own test
	}
	
	// otherwise, use our own simple method
	try {
		self::is_minimum_length($data, strlen('a@b.c'));
	}
	catch (ValidationException $e) {
		throw new ValidationException('emailaddress', 0);
	}
	
	$email_at_exists = strpos($data, '@');
	$email_dot_exists = strpos($data, '.');
	if ($email_at_exists && $email_dot_exists) {
		return;
	}
	throw new ValidationException('emailaddress');
}

/*--- slug ---*/
private static function is_slug($data) {
	if (preg_match('/[^a-z0-9-]/', $data)) {
		throw new ValidationException('slug');
	}
}

/*--- id ---*/
private static function is_id($data) {
	if (preg_match('/[^0-9]/', $data)) {
		throw new ValidationException('id');
	}
	
	try {
		self::is_maximum_length($data, 11);
	}
	catch (ValidationException $e) {
		throw new ValidationException('id_length', 0);
	}
}

/*--- multiple-ids ---*/
private static function is_multiple_ids($data, $amount=false) {
	if (strpos($data, '|') === false) {
		throw new ValidationException('multiple_ids');
	}
	if (preg_match('/[^0-9\|]/', $data)) {
		throw new ValidationException('multiple_ids');
	}
	
	$ids_array = explode('|', $data);
	try {
		foreach ($ids_array as $single_id) {
			self::is_id($single_id);
		}
	}
	catch (ValidationException $e) {
		throw new ValidationException('multiple_ids', 0);
	}
	
	if ($amount && count($ids_array) != $amount) {
		throw new ValidationException('multiple_ids_amount');
	}
}

/*--- hash ---*/
private static function is_hash($data) {
	if (preg_match('/[^a-fA-F0-9]/', $data)) {
		throw new ValidationException('hash');
	}
}

/*--- tags ---*/
private static function is_tags($data) {
	if (preg_match('/[^a-zA-Z,\/ -]/', $data)) {
		throw new ValidationException('tags');
	}
}

/*--- url ---*/
private static function is_url($data, $type='absolute') {
	if ($type == 'relative' && preg_match('/[^a-zA-Z0-9\/ -]/', $data)) {
		throw new ValidationException('url_relative');
	}
	if ($type == 'absolute' && preg_match('/[^a-zA-Z0-9\:\/\?\=\& -]/', $data)) {
		throw new ValidationException('url_absolute');
	}
}

/*--- filename ---*/
private static function is_filename($data) {
	if (preg_match('/[^a-zA-Z0-9_.()-]/', $data)) {
		throw new ValidationException('filename');
	}
	
	try {
		self::is_minimum_length($data, 2);
		self::is_maximum_length($data, 75);
	}
	catch (ValidationException $e) {
		throw new ValidationException('filename_length', 0);
	}
}

/*--- name ---*/
// currently the same as a city
private static function is_name($data) {
	if (preg_match('/[^'.self::$regex_utf8_alpha.'\' -]/u', $data)) {
		throw new ValidationException('name');
	}
	
	try {
		self::is_minimum_length($data, 2);
		self::is_maximum_length($data, 75);
	}
	catch (ValidationException $e) {
		throw new ValidationException('name_length', 0);
	}
}

/*--- city ---*/
// currently the same as a name
private static function is_city($data) {
	if (preg_match('/[^'.self::$regex_utf8_alpha.'\' -]/u', $data)) {
		throw new ValidationException('city');
	}
	
	try {
		self::is_minimum_length($data, 2);
		self::is_maximum_length($data, 75);
	}
	catch (ValidationException $e) {
		throw new ValidationException('city_length', 0);
	}
}

/*--- postal code ---*/
private static function is_postalcode($data) {
	if (preg_match('/[1-9]{1}[0-9]{3}( |  |-)?[a-zA-Z]{2}/', $data) == false) {
		throw new ValidationException('postalcode');
	}
}

/*--- phone number ---*/
private static function is_phonenumber($data) {
	if (preg_match('/[^0-9 \(\)+-]/', $data)) {
		throw new ValidationException('phonenumber');
	}
	
	$stripped_phonenumber = preg_replace('/[^0-9+]/', '', $data);
	
	// change international numbers
	if (strpos($stripped_phonenumber, '+310') === 0) {
		$stripped_phonenumber = substr($stripped_phonenumber, strlen('+31'));
	}
	if (strpos($stripped_phonenumber, '+31') === 0) {
		$stripped_phonenumber = '0'.substr($stripped_phonenumber, strlen('+31'));
	}
	if ($stripped_phonenumber[0] != '+' && $stripped_phonenumber[0] != '0') {
		$stripped_phonenumber = '0'.$stripped_phonenumber;
	}
	
	try {
		self::is_minimum_length($stripped_phonenumber, 10);
		self::is_maximum_length($stripped_phonenumber, 25);
	}
	catch (ValidationException $e) {
		throw new ValidationException('phonenumber_length', 0);
	}
}

/*--- gender ---*/
private static function is_gender($data) {
	if ($data != 'female' && $data != 'male') {
		throw new ValidationException('gender');
	}
}

/*--- checkbox ---*/
private static function is_checkbox($data, $checked_value='on') {
	if ($data != $checked_value) {
		throw new ValidationException('checkbox');
	}
}

/*------------------------------------------------------------------------------
	validators: alias methods
------------------------------------------------------------------------------*/

private static function is_req($data) {
	self::is_required($data);
}
private static function is_min($data, $minimal_length) {
	self::is_minimum_length($data, $minimal_length);
}
private static function is_max($data, $maximum_length) {
	self::is_maximum_length($data, $maximum_length);
}
private static function is_nonl($data) {
	self::is_no_newlines($data);
}

private static function is_int($data) {
	self::is_integer($data);
}
private static function is_str($data) {
	self::is_string($data);
}
private static function is_bool($data) {
	self::is_boolean($data);
}
private static function is_arr($data) {
	self::is_array($data);
}

private static function is_email($data) {
	self::is_emailaddress($data);
}
private static function is_file($data) {
	self::is_filename($data);
}
private static function is_postal($data) {
	self::is_postalcode($data);
}
private static function is_phone($data) {
	self::is_phonenumber($data);
}

}

/*------------------------------------------------------------------------------
	custom exceptions
------------------------------------------------------------------------------*/

class InputHelperException extends Exception {
	public $errors = false;
	
	// test if an error EXists
	public function ifex($error_key) {
		if (isset($this->errors[$error_key]) == false) {
			return false;
		}
		
		return true;
	}
	
	// test if an error EXists and is EQual to a specific value
	public function ifexeq($error_key, $equals) {
		if (isset($this->errors[$error_key]) == false) {
			return false;
		}
		if ($equals && $this->errors[$error_key] != $equals) {
			return false;
		}
		
		return true;
	}
	
	// the oposite, tests if an error EXists and Not EQual
	public function ifexneq($error_key, $not_equals) {
		if (isset($this->errors[$error_key]) == false) {
			return false;
		}
		if ($not_equals && $this->errors[$error_key] == $not_equals) {
			return false;
		}
		
		return true;
	}
}

class InputException extends InputHelperException {}
class ValidationException extends InputHelperException {}

?>