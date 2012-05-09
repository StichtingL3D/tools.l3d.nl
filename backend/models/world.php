<?php
/*------------------------------------------------------------------------------
	world model
------------------------------------------------------------------------------*/

class world extends aww_table {

public function __toString() {
	return $this->Name;
}

public function get_build_rights() {
	$build_rights_attribute = 17;
	
	$sql = "SELECT `Value`
		FROM `aws_attrib`, `aws_world`
		WHERE
			`aws_attrib`.`ID` = ".$build_rights_attribute."
			AND
			`aws_attrib`.`World` = `aws_world`.`ID`
			AND
			`aws_world`.`Name` = '%s'
		;";
	
	return $this->db_select('field', $sql, $this->Name);
}

public function build_rights_for($citizen_id) {
	$build_rights = $this->get_build_rights();
	
	return $this->citizen_id_in_rights_string($citizen_id, $build_rights);
}

private function citizen_id_in_rights_string($citizen_id, $rights_string) {
	// nobody and everybody
	if (empty($rights_string)) {
		return false;
	}
	if ($rights_string == '*') {
		return true;
	}
	
	// included (1234)
	$padded_rights_string = ' '.$rights_string.' ';
	$padded_citizen_id = ' '.$citizen_id.' ';
	if (strpos($padded_rights_string, $padded_citizen_id) !== false) {
		return true;
	}
	
	// excluded (-1234)
	$negative_citizen_id = ' -'.$citizen_id.' ';
	if (strpos($padded_rights_string, $negative_citizen_id) !== false) {
		return false;
	}
	
	// listed
	if (strpos($padded_rights_string, '~')) {
		$complete_rights_string = $this->convert_rights_string($padded_rights_string);
		if (strpos($complete_rights_string, $padded_citizen_id) !== false) {
			return true;
		}
	}
	
	return false;
}

private function convert_rights_string($rights_string) {
	$complete_rights_string = ' ';
	
	preg_match_all('/([0-9]+)( )?~( )?([0-9]+)/', $rights_string, $lists, PREG_SET_ORDER);
	foreach ($lists as $list) {
		$first = $list[1];
		$last = $list[4];
		$all = ' ';
		for ($i=$first; $i<=$last; $i++) {
			$all .= $i.' ';
		}
		
		$complete_rights_string .= $all.' ';
	}
	
	return $complete_rights_string;
}

/*------------------------------------------------------------------------------
	override extended model
------------------------------------------------------------------------------*/
protected $table = 'aws_world';

public function get_property($key) {
	if ($key == 'Password') {
		throw new Exception('can not get "'.$key.'" directly');
	}
	
	return parent::get_property($key);
}

public function get_object() {
	parent::get_object();
	unset($this->table_data['Password']);
	
	return $this->table_data;
}

}
