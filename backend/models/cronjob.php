<?php
/*------------------------------------------------------------------------------
	cronjob
------------------------------------------------------------------------------*/

class cronjob_model extends model {

protected $table = 'cronjobs';

/*------------------------------------------------------------------------------
	execute the job itself
------------------------------------------------------------------------------*/
public function execute() {
	// starting up
	$this->status = 'starting';
	$this->update_object();
	$full_function = $this->filename.'_cronjob::'.$this->function;
	
	load::cronjob($this->filename);
	if (is_callable($full_function) == false) {
		throw new Exception('function '.$full_function.' not found');
	}
	
	// run! lola! run!
	$this->status = 'running';
	$this->update_object();
	call_user_func_array($full_function, $this->arguments);
	
	// adjust 'now' jobs so they don't get cleaned-up directly
	if ($this->start_from == '0') {
		$this->start_from = time();
		$this->result = 'start_from was now';
		$this->update_object();
	}
	
	// done
	$this->status = 'done';
	$this->update_object();
}

/*------------------------------------------------------------------------------
	decode json arguments
------------------------------------------------------------------------------*/
public function get_property($key) {
	$value = parent::get_property($key);
	
	if ($key == 'arguments' && empty($value) == false) {
		$this->table_data[$key] = json_decode($value, true);
	}
	elseif ($key == 'arguments' && empty($value)) {
		$this->table_data[$key] = array();
	}
	
	return $this->table_data[$key];
}

}
?>