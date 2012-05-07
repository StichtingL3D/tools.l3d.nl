<?php
/*------------------------------------------------------------------------------
	object model
------------------------------------------------------------------------------*/

class object extends table {

public function __toString() {
	return $this->filename.' ('.$this->type.')';
}

private function get_pretty_type() {
	switch ($this->type) {
		case 'avatars':  return 'avatar';   break;
		case 'models':   return 'object';   break;
		case 'seqs':     return 'beweging'; break;
		case 'sounds':   return 'geluid';   break;
		case 'textures': return 'textuur';  break;
	}
}

public function select($keys=false, $where=false, $group=false, $order=false, $limit=100) {
	if ($order == false) {
		$order = '-upload_time';
	}
	
	$all = parent::select($keys, $where, $group, $order, $limit);
	
	// post processing, add images and citizen names
	foreach ($all as &$object) {
		if (isset($object['objectpath_id'])) {
			try {
				$objectpath = new objectpath($object['objectpath_id']);
			}
			catch (Exception $e) {
				$objectpath = false;
			}
			
			if ($objectpath && $object['type'] == 'textures' && strpos($object['filename'], '.bmp') === false) {
				$object['image'] = 'http://'.$objectpath->domain.'.props.l3d.nl/textures/'.$object['filename'];
			}
		}
		
		if (isset($object['citizen_id'])) {
			try {
				$citizen = new citizen($object['citizen_id']);
			}
			catch (Exception $e) {
				$citizen = false;
			}
			$object['citizen'] = $citizen;
		}
	}
	
	return $all;
}

public function select_recent() {
	$recent = time()-(60*60*24*14); // two weeks
	
	$where = array(
		array('upload_time', 'IS NOT', 'NULL'),
		'AND',
		array('upload_time', '>', $recent),
	);
	$order = '-upload_time';
	
	return $this->select(false, $where);
}

public function select_popular() {
	return array();
}

public function select_mine($citizen_id) {
	$recent = time()-(60*60*24*14); // two weeks
	
	$where = array(
		array('citizen_id', 'IS NOT', 'NULL'),
		'AND',
		array('citizen_id' => $citizen_id),
	);
	
	return $this->select(false, $where);
}

public function add($type, $filename, $objectpath_id, $citizen_id=null) {
	$allowed_types = array_flip(array('avatars','models','seqs','sounds','textures'));
	if (isset($allowed_types[$type]) == false) {
		$allowed_list = implode(' or ', array_flip($allowed_types));
		throw new Exception('wrong object type ('.$type.'), allowed: '.$allowed_list);
	}
	
	try {
		$objectpath = new objectpath($objectpath_id);
		$citizen = new citizen($citizen_id);
	}
	catch (Exception $e) {
		throw new Exception('wrong objectpath or citizen id', 0);
	}
	
	$new_data = array(
		'type' => $type,
		'filename' => $filename,
		'upload_time' => time(),
		'objectpath_id' => $objectpath_id,
		'citizen_id' => $citizen_id,
	);
	
	return $this->insert($new_data);
}

/*------------------------------------------------------------------------------
	override extended model
------------------------------------------------------------------------------*/
public function get_property($key) {
	if (strpos($key, 'type_') === 0) {
		return 'type_'.$this->type;
	}
	if ($key == 'pretty_type') {
		return $this->get_pretty_type();
	}
	
	return parent::get_property($key);
}

}
