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
