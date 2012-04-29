<?php
/*------------------------------------------------------------------------------
	uploading, validating and storing uploaded files
	
	upload::check($mime, $min_size, $max_size)
	upload::move($from, $to_data_path, $to_name)
------------------------------------------------------------------------------*/

class upload {

private static $file;
private static $mime;

public static function construct() {
	if (ini_get('file_uploads') == false) {
		throw new UploadException('base - file_uploads disabled');
	}
}

public static function set_file($file=false) {
	if ($file == false) {
		if (empty($_FILES)) {
			throw new UploadException('base - empty $_FILES, check enctype="multipart/form-data"');
		}
		$file = reset($_FILES);
	}
	
	if ($file['error']) {
		throw new UploadException($file['error']);
	}
	
	self::$file = $file;
}

/*------------------------------------------------------------------------------
	gather and validate uploaded files
------------------------------------------------------------------------------*/

public static function check($mime=false, $min_size=false, $max_size=false) {
	if (empty(self::$file)) {
		self::set_file();
	}
	
	self::check_security();
	if ($mime) {
		self::check_mime($mime);
	}
	if ($min_size || $max_size) {
		self::check_size($min_size, $max_size);
	}
	
	return array(
		'name' => input::validate(self::$file['name'], 'filename', $silent=true),
		'mime' => self::$mime,
		'path' => self::$file['tmp_name'],
	);
}

private static function check_security() {
	if (is_uploaded_file(self::$file['tmp_name']) == false) {
		error::hack('is_uploaded_file() failed');
		throw new UploadException('check - is_uploaded_file() failed');
	}
	
	if (is_readable(self::$file['tmp_name']) == false) {
		throw new UploadException(UPLOAD_ERR_NO_TMP_DIR);
	}
	
	// check for xss hack using a (text)script inside a (binary)file
	// look at the first 256 bytes for the 'script' tag
	// * http://tweakers.net/nieuws/47643/XSS-exploit-door-Microsoft-betiteld-als-by-design.html
	// * http://www.splitbrain.org/blog/2007-02/12-internet_explorer_facilitates_cross_site_scripting
	// * http://weblog.philringnalda.com/2004/04/06/getting-around-ies-mime-type-mangling
	$start_of_file = strtolower(file_get_contents(self::$file['tmp_name'], NULL, NULL, 0, 256));
	if (strpos($start_of_file, 'script') !== false) {
		error::hack('file contains a script tag');
		throw new UploadException('check - file contains a script tag');
	}
}

private static function check_mime($expected_mime) {
	// get actual mime
	if (function_exists('mime_content_type') == true) {
		self::$mime = mime_content_type(self::$file['tmp_name']);
	}
	elseif (function_exists('finfo_open') == true && function_exists('finfo_file') == true) {
		$file_mimetype_info = finfo_open(FILEINFO_MIME_TYPE);
		self::$mime = finfo_file($file_mimetype_info, self::$file['tmp_name']);
	}
	
	// uploaded mime is different from actual mime, something strange
	if (self::$file['type'] != self::$mime) {
		error::hack('file has different mimetype');
		throw new UploadException('check - file has different mimetype');
	}
	
	// mime is not what was requested of the user
	if (strpos($expected_mime, '/') === false) {
		// expectations are broad ('image' instead of 'image/jpeg')
		$broad_mime = substr(self::$mime, 0, strpos(self::$mime, '/'));
		if ($broad_mime != $expected_mime) {
			throw new UploadException('check - wrong mimetype - '.$expected_mime.' was expected, '.$broad_mime.' ('.self::$mime.') is the reality');
		}
	}
	elseif (self::$mime != $expected_mime) {
		throw new UploadException('check - wrong mimetype - '.$expected_mime.' was expected, '.self::$mime.' is the reality');
	}
}

private static function check_size($minimal, $maximal) {
	// get actual size
	$actual_size = filesize(self::$file['tmp_name']);
	
	// uploaded size is different from actual size, something strange
	if (self::$file['size'] != $actual_size) {
		error::hack('file has different filesize');
		throw new UploadException('check - file has different filesize');
	}
	
	// size is not what was requested of the user
	if ($actual_size < $minimal) {
		throw new UploadException('check - file is smaller ('.$actual_size.') then minimum ('.$minimal.')');
	}
	if ($actual_size > $maximal) {
		throw new UploadException('check - file is bigger ('.$actual_size.') then maximum ('.$maximal.')');
	}
}

/*------------------------------------------------------------------------------
	moving uploaded files
------------------------------------------------------------------------------*/

public static function move($from, $to_data_path=false, $to_name=false) {
	// prepare
	if ($to_data_path == false) {
		$to_data_path = 'upload/files/'.CONTROLLER;
	}
	
	$places = json_decode(PLACES, true);
	$to = $places['data'].$to_data_path;
	$to_full = $to.$to_name;
	
	// check
	if (is_readable($from) == false) {
		throw new UploadException('move - can not read path to move from');
	}
	if (strpos($to, $places['data']) !== 0) {
		throw new UploadException('move - can not move outside data path');
	}
	if ($to !== realpath($to)) {
		throw new UploadException('move - invalid path to move to');
	}
	if (is_writable($to) == false) {
		throw new UploadException('move - can not write, check safe_mode/open_basedir');
	}
	if (input::validate($to_name, 'filename', $silent=true) == false) {
		throw new UploadException('move - invalid filename to rename to');
	}
	if (file_exists($to_full)) {
		throw new UploadException('move - file already exists');
	}
	
	// move
	$result = move_uploaded_file($from, $to_full);
	if ($result == false) {
		throw new UploadException('move - failed, check safe_mode/open_basedir');
	}
}

/*------------------------------------------------------------------------------
	helpers
------------------------------------------------------------------------------*/

public static function get_max_filesize() {
	return self::phpini_to_bytes(ini_get('upload_max_filesize'));
}

private static function phpini_to_bytes($value) {
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

}
upload::construct();

/*------------------------------------------------------------------------------
	custom exceptions
------------------------------------------------------------------------------*/

class UploadException extends Exception {

public $errors = false;

public function __construct($message=null, $code=0, Exception $previous=null) {
	// default upload errors
	if (is_numeric($message)) {
		$code = $message;
		switch ($code) {
			// see http://www.php.net/manual/en/features.file-upload.errors.php
			
			case '1': // UPLOAD_ERR_INI_SIZE
				$message = 'The uploaded file exceeds the upload_max_filesize directive in php.ini.';
				break;
			
			case '2': // UPLOAD_ERR_FORM_SIZE
				$message = 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form.';
				break;
			
			case '3': // UPLOAD_ERR_PARTIAL
				$message = 'The uploaded file was only partially uploaded.';
				break;
			
			case '4': // UPLOAD_ERR_NO_FILE
				$message = 'No file was uploaded.';
				break;
			
			case '6': // UPLOAD_ERR_NO_TMP_DIR
				$message = 'Missing a temporary folder.';
				break;
			
			case '7': // UPLOAD_ERR_CANT_WRITE
				$message = 'Failed to write file to disk.';
				break;
			
			case '8': // UPLOAD_ERR_EXTENSION
				$message = 'File upload stopped by extension.';
				break;
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
