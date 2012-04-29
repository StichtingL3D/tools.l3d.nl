<?php
/*------------------------------------------------------------------------------
	objectpath model
------------------------------------------------------------------------------*/

class objectpath extends table {

private $ftp_connection = false;
private $ftp_config;

public function __toString() {
	return $this->domain.'props.l3d.nl';
}

/*------------------------------------------------------------------------------
	adding and moving files
------------------------------------------------------------------------------*/
public function add_file($type, $local_file, $remote_filename) {
	$remote_file = $this->ftp_config['path'].$type.'/'.$remote_filename;
	$this->remote_upload($local_file, $remote_file);
}

public function change_type($filename, $old_type, $new_type) {
	$old_file = $this->ftp_config['path'].$old_type.'/'.$filename;
	$new_file = $this->ftp_config['path'].$new_type.'/'.$filename;
	
	$this->remote_rename($old_file, $new_file);
}

/*------------------------------------------------------------------------------
	remote files
------------------------------------------------------------------------------*/
private function remote_connect($dir=false) {
	$this->ftp_connection = ftp_connect($this->ftp_config['host']);
	if ($this->ftp_connection == false) {
		throw new Exception('ftp fails to connect');
	}
	
	$remote_login_check = ftp_login($this->ftp_connection, $this->ftp_config['user'], base64_decode($this->ftp_config['pass']));
	if ($remote_login_check == false) {
		throw new Exception('ftp fails to login');
	}
	
	#ftp_pasv($this->ftp_connection, true); // remedy for all kind of ftp errors, see php.net/function.ftp-put.php.html#90518 and more
	
	if ($dir) {
		$this->remote_chdir($dir);
	}
}

private function remote_goto($dir) {
	if ($this->ftp_connection == false) {
		$this->remote_connect();
	}
	
	$remote_chdir_check = ftp_chdir($this->ftp_connection, $remote_path);
	if ($remote_chdir_check == false) {
		throw new Exception('ftp fails to change directory');
	}
}

private function remote_upload($local_file, $remote_file) {
	if ($this->ftp_connection == false) {
		$this->remote_connect();
		
		if (strpos($remote_file, '/')) {
			$remote_path = dirname($remote_file);
			$remote_file = basename($remote_file);
			
			$this->remote_goto($remote_path);
		}
	}
	
	$remote_exists_check = ftp_size($this->ftp_connection, $remote_file);
	if ($remote_exists_check > 0) {
		throw new Exception('file already exists');
	}
	
	$remote_upload_check = ftp_put($this->ftp_connection, $remote_file, $local_file, FTP_BINARY);
	if ($remote_upload_check == false) {
		throw new Exception('ftp fails upload');
	}
}

private function remote_rename($old_file, $new_file) {
	if ($this->ftp_connection == false) {
		$this->remote_connect();
	}
	
	$remote_exists_check = ftp_size($this->ftp_connection, $new_file);
	if ($remote_exists_check > 0) {
		throw new Exception('file already exists');
	}
	
	$remote_rename_check = ftp_rename($this->ftp_connection, $old_file, $new_file);
	if ($remote_rename_check == false) {
		throw new Exception('ftp fails rename');
	}
}

private function remote_disconnect() {
	$remote_close_check = ftp_close($this->ftp_connection);
	if ($remote_close_check == false) {
		throw new Exception('file fails to close');
	}
}

/*------------------------------------------------------------------------------
	override extended model
------------------------------------------------------------------------------*/
public function __construct($id=null) {
	if (is_numeric($id) == false) {
		$this->index_key = 'domain';
		$this->id_isint = false;
	}
	
	parent::__construct($id);
	
	$this->ftp_config = new config('upload/objectpaths', $this->domain);
}

public function get_property($key) {
	if ($key == 'password') {
		throw new Exception('can not get "'.$key.'" directly');
	}
	
	return parent::get_property($key);
}

public function get_object() {
	parent::get_object();
	unset($this->table_data['password']);
	
	return $this->table_data;
}

public function __destruct() {
	try {
		$this->remote_disconnect();
	}
	catch (Exception $e) {
		// exceptions are not allowed
	}
	
	parent::__destruct();
}

}
