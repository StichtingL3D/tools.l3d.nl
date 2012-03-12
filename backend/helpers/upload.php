<?php
/*------------------------------------------------------------------------------
	uploading, validating and storing uploaded files
	
	upload::check($validation)
		$validation: an array of validation rules
			when expecting multiple files, a nested array with the name as key
		returns
			a string with temporary path
			or an array with temporary paths when multiple files are uploaded
------------------------------------------------------------------------------*/

/*------------------------------------------------------------------------------
	otherwise one of the following messages is given:
		* file_uploads disabled
		* wrong mime-type
		* hacking attempt
		* not writable
		* file exists
		* move failed
------------------------------------------------------------------------------*/

class upload {

/*------------------------------------------------------------------------------
	gather and validate uploaded files
------------------------------------------------------------------------------*/

public static function check($validation_rules) {
	if (ini_get('file_uploads') == false) {
		throw new UploadException('file_uploads disabled');
	}
	if (empty($_FILES)) {
		// could be that the enctype="multipart/form-data" is not set
		throw new UploadException(UPLOAD_ERR_NO_FILE);
	}
	
	$fileinfo = array();
	if (count($_FILES) > 1) {
		foreach ($validation_rules as $fieldname => $single_rules) {
			
			#TODO: try-catch
			
			$single_rules = self::convert_ruleset($single_rules);
			
			if (empty($_FILES[$filename]) && isset($single_rules['required']) && isset($single_rules['req'])) {
				throw new ValidationException('required');
			}
			elseif (empty($_FILES[$filename])) {
				continue;
			}
			
			$file = $_FILES[$filename];
			#TODO: input::secure() ?
			$fileinfo[$fieldname] = upload::validate($file, $single_rules);
			
		}
	}
	else {
		$file = reset($_FILES);
		$fileinfo = upload::validate($file, $validation_rules);
	}
	
	return $fileinfo;
}

// copy from input helper
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
	moving uploaded files
------------------------------------------------------------------------------*/

public static function move($from, $to_data_path, $to_name) {
	// prepare
	// check
	// move
}

/*------------------------------------------------------------------------------
	actual validators
	
	req|required
	min|minimum_filesize => #
	max|maximum_filesize => #
	mime|type|mimetype => *
------------------------------------------------------------------------------*/

private static function validate($data, $rules) {
	
}

/*------------------------------------------------------------------------------
	validators: basics
------------------------------------------------------------------------------*/

// is_required is handled directly by upload::validate()

private static function is_minimum_filesize($data, $minimal_filesize) {
	if (mb_strlen($data) < $minimal_filesize) {
		throw new ValidationException('minimum_filesize');
	}
}

private static function is_maximum_filesize($data, $maximum_filesize) {
	if (mb_strlen($data) > $maximum_filesize) {
		throw new ValidationException('maximum_filesize');
	}
}

private static function is_mimetype($data, $mimetype) {
	#if (preg_match('/[^a-z0-9-]/', $data)) {
	#	throw new ValidationException('mimetype');
	#}
}

/*------------------------------------------------------------------------------
	validators: alias methods
------------------------------------------------------------------------------*/

private static function is_req($data) {
	self::is_required($data);
}
private static function is_min($data, $minimal_filesize) {
	self::is_minimum_filesize($data, $minimal_filesize);
}
private static function is_max($data, $maximum_filesize) {
	self::is_maximum_filesize($data, $maximum_filesize);
}
private static function is_mime($data, $mimetype) {
	self::is_mimetype($data, $mimetype);
}
private static function is_type($data, $mimetype) {
	self::is_mimetype($data, $mimetype);
}

/*------------------------------------------------------------------------------
	custom exceptions
------------------------------------------------------------------------------*/

class UploadException extends Exception {
	public $errors = false;
	
	public function __construct($message=null, $code=0, Exception $previous=null) {
		// default upload errors
		if (is_numeric($message)) {
			$code = $message;
			switch ($message) {
				/*
					see http://www.php.net/manual/en/features.file-upload.errors.php
					1: UPLOAD_ERR_INI_SIZE
					2: UPLOAD_ERR_FORM_SIZE
					3: UPLOAD_ERR_PARTIAL
					4: UPLOAD_ERR_NO_FILE
					6: UPLOAD_ERR_NO_TMP_DIR
					7: UPLOAD_ERR_CANT_WRITE
					8: UPLOAD_ERR_EXTENSION
				*/
				case '0': $message = 'There is no error, the file uploaded with success.'; break;
				case '1': $message = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.'; break;
				case '2': $message = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.'; break;
				case '3': $message = 'The uploaded file was only partially uploaded.'; break;
				case '4': $message = 'No file was uploaded.'; break;
				case '6': $message = 'Missing a temporary folder.'; break;
				case '7': $message = 'Failed to write file to disk.'; break;
				case '8': $message = 'File upload stopped by extension.'; break;
			}
		}
		
		parent::__construct($message, $code, $previous);
	}
	
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

}



/*------------------------------------------------------------------------------
	old als vanzelf plugin
------------------------------------------------------------------------------*/

class upload {

private $places;
private $loader;

private $name;
private $state = 0;

private $file; // array(name, file_name, file_type, location_old, location_new)
private $file_name;
private $file_type;

if (ini_get('file_uploads') == false) {
	throw new AVZ_Exception('file_uploads disabled', -1);
}

// $options: mimetype | new_location
public function _start($options) {
	if (isset($_FILES) == false || empty($_FILES) == true) {
		// could be that the enctype="multipart/form-data" is not set
		$this->throw_upload_error('UPLOAD_ERR_NO_FILE', UPLOAD_ERR_NO_FILE);
	}
	
	// set name automaticly
	if (count($_FILES) === 1) {
		$this->name = key($_FILES);
		
		// continue
		$this->state = 1;
	}
	
	if (isset($options['mimetype']) == false) {
		$options['mimetype'] = false;
	}
	
	if (empty($options['new_location']) == true) {
		$options['new_location'] = MODULE;
	}
	
	$this->check($options['mimetype']);
	$this->prepare_move($options['new_location']);
	$this->move();
	
	// get $upload->get_fileinfo();
}

// set the name of the uploaded file (the key in $_FILES[])
public function set_name($name) {
	$this->name = $name;
	
	// continue
	$this->state = 1;
}

public function get_fileinfo() {
	$file = $this->file;
	
	// array(name, file_name, file_type, location_old, location_new)
	// * name: execute prepare_move()
	// * file_name: execute prepare_move()
	// * file_type: execute check()
	// * location_old: re-creatable, also done in move()
	// * location_new: done in move(), semi re-creatable
	
	if (empty($file['location_old']) == true) {
		$file['location_old'] = $_FILES[$this->name]['tmp_name'];
	}
	if (empty($file['location_new']) == true) {
		$file['location_new'] = $this->places['data'].MODULE.SLASH.$file['file_name'];
	}
	
	return $file;
}

/*------------------------------------------------------------------------------
	external helper functions ---*/
public function phpini_to_bytes($value) {
	// function from http://nl2.php.net/manual/en/function.ini-get.php
	$letter = strtolower($value[strlen($value)-1]);
	switch($letter) {
		case 'g':
			$value *= 1024;
		case 'm':
			$value *= 1024;
		case 'k':
			$value *= 1024;
	}
	
	return $value;
}

/*------------------------------------------------------------------------------
	internal helper functions ---*/
private function throw_upload_error($message, $code='') {
	if (is_numeric($message) == true) {
		$code = $message;
		switch ($message) {
			case '0': $message = 'UPLOAD_ERR_OK';			break;
			case '1': $message = 'UPLOAD_ERR_INI_SIZE';		break;
			case '2': $message = 'UPLOAD_ERR_FORM_SIZE';	break;
			case '3': $message = 'UPLOAD_ERR_PARTIAL';		break;
			case '4': $message = 'UPLOAD_ERR_NO_FILE';		break;
			case '6': $message = 'UPLOAD_ERR_NO_TMP_DIR';	break;
			case '7': $message = 'UPLOAD_ERR_CANT_WRITE';	break;
			case '8': $message = 'UPLOAD_ERR_EXTENSION';	break;
		}
	}
	
	switch ($code) {
		case '0': $desc = 'There is no error, the file uploaded with success.'; break;
		case '1': $desc = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.'; break;
		case '2': $desc = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.'; break;
		case '3': $desc = 'The uploaded file was only partially uploaded.'; break;
		case '4': $desc = 'No file was uploaded.'; break;
		case '6': $desc = 'Missing a temporary folder.'; break;
		case '7': $desc = 'Failed to write file to disk.'; break;
		case '8': $desc = 'File upload stopped by extension.'; break;
	}
	
	$e = new AVZ_Exception($message, $code);
	$e->addDesc($desc);
	throw $e;
}

/*------------------------------------------------------------------------------
	check the uploaded file ---*/
public function check($required_mime=false) {
	if ($this->state < 1) {
		$e = new AVZ_Exception('use set_name() first');
		$e->addDesc('there are multiple uploaded files. select which you want to use, using upload::set_name().');
		throw $e;
	}
	
	$this->check_file();
	
	if ($required_mime !== false) {
		$actual_mime = $this->check_mime();
		
		if (strpos($required_mime, '/') === false) {
			if (strpos($actual_mime, $required_mime) !== 0) {
				$e = new AVZ_Exception('wrong mime-type');
				$e->addDesc('required: '.$required_mime.' | actual: '.$actual_mime);
				throw $e;
			}
		}
		elseif ($actual_mime != $required_mime) {
			$e = new AVZ_Exception('wrong mime-type');
			$e->addDesc('required: '.$required_mime.' | actual: '.$actual_mime);
			throw $e;
		}
	}
	
	// continue
	$this->state = 2;
}

private function check_file() {
	$file = $_FILES[$this->name];
	
	// error
	if ($file['error'] != '0') {
		$this->throw_upload_error($file['error']);
	}
	
	// is uploaded file
	if (is_uploaded_file($file['tmp_name']) == false) {
		$e = new AVZ_Exception('hacking attempt');
		$e->addDesc('is_uploaded_file() failed.');
		
		#TODO# log hack in some other way as well?
		
		throw $e;
	}
	
	// is readable
	if (is_readable($file['tmp_name']) == false) {
		$this->throw_upload_error('UPLOAD_ERR_NO_TMP_DIR', UPLOAD_ERR_NO_TMP_DIR);
	}
	
	// security, xss hack using a (text)script inside a (binary)file
	// check the first 256 bytes for the 'script' tag
	// * http://tweakers.net/nieuws/47643/XSS-exploit-door-Microsoft-betiteld-als-by-design.html
	// * http://www.splitbrain.org/blog/2007-02/12-internet_explorer_facilitates_cross_site_scripting
	// * http://weblog.philringnalda.com/2004/04/06/getting-around-ies-mime-type-mangling
	$start_of_file = strtolower(file_get_contents($file['tmp_name'], NULL, NULL, 0, 256));
	if (strpos($start_of_file, 'script') == true) {
		$e = new AVZ_Exception('hacking attempt');
		$e->addDesc('file contains a script tag.');
		
		#TODO# log hack in some other way as well?
		
		throw $e;
	}
}

private function check_mime() {
	$file = $_FILES[$this->name];
	
	$mime = '';
	if (function_exists('mime_content_type') == true) {
		$mime = mime_content_type($file['tmp_name']);
	}
	elseif (function_exists('finfo_open') == true && function_exists('finfo_file') == true) {
		$file_mimetype_info = finfo_open(FILEINFO_MIME_TYPE);
		$mime = finfo_file($file_mimetype_info, $file['tmp_name']);
	}
	
	$this->file['file_type'] = $mime;
	
	return $mime;
}

/*------------------------------------------------------------------------------
	move the uploaded file ---*/
public function prepare_move($suggested_location=false) {
	if ($this->state < 2) {
		$e = new AVZ_Exception('use check() first');
		$e->addDesc('first check the file for validility, using upload::check().');
		throw $e;
	}
	$file = $_FILES[$this->name];
	
	// writable data folder
	$new_location = $this->places['data'];
	$new_location .= ($suggested_location != false) ? $suggested_location : MODULE;
	if (is_writable($new_location) == false) {
		$e = new AVZ_Exception('not writable');
		$e->addDesc('can\'t move the file to its new destination. safe_mode/open_basedir trouble?');
		throw $e;
	}
	
	$ext_start = strrpos($file['name'], '.');
	$extention = strtolower(substr($file['name'], $ext_start));
	$filename = substr($file['name'], 0, $ext_start);
	
	// create filename, max 30 chars
	$safe_filename = preg_replace('/[^a-zA-Z0-9_-]+/', '', $filename);
	$new_filename = substr($safe_filename, 0, 30);
	
	// test existence
	if (file_exists($new_location.SLASH.$new_filename.$extention) == true) {
		// add time ( '_'+time ) to the filename
		// first, shorten even further. '_'+time = 11, removing 10 is enough
		$new_filename = substr($new_filename, 0, 20);
		$new_filename = $new_filename.'_'.time();
	}
	
	$this->file['name'] = $new_filename;
	$this->file['file_name'] = $new_filename.$extention;
	
	$this->file['location_old'] = $file['tmp_name'];
	$this->file['location_new'] = $new_location;
	
	$this->state = 3;
	
	return $this->file;
}

public function move($new_filename='') {
	if ($this->state < 3) {
		$this->prepare_move();
	}
	if (empty($new_filename) == false) {
		$this->file['file_name'] = $new_filename;
	}
	$file = $_FILES[$this->name];
	
	$this->file['location_new'] .= SLASH.$this->file['file_name'];
	
	// last existence check (move_uploaded_file overwrites otherwise)
	if (file_exists($this->file['location_new']) == true) {
		$e = new AVZ_Exception('file exists');
		$e->addDesc('tried renaming but the new destination -still- already exists.');
		throw $e;
	}
	
	// now we can make the final action :)
	$result = move_uploaded_file($this->file['location_old'], $this->file['location_new']);
	if ($result == false) {
		$e = new AVZ_Exception('move failed');
		$e->addDesc('failed moving the file to its new desctination. safe_mode/open_basedir trouble?');
		throw $e;
	}
	
	$this->state = 4;
	
	return $this->file['file_name'];
}

}
?>