<?php
/*------------------------------------------------------------------------------
	form
------------------------------------------------------------------------------*/

class form_model extends model {

protected $table = 'forms';
protected $id_isint = false;

/*------------------------------------------------------------------------------
	check for a valid form as time and session-id
------------------------------------------------------------------------------*/
public function check() {
	if ($this->status != 'ajax') {
		$this->status = 'checking';
	}
	
	// check for token lifetime, tokens expire so they can't be collected
	if ($this->status == 'ajax') {
		$max_oldness = time() - (60*60*1); // one hour
		if ($this->sent_at < $max_oldness) {
			throw new Exception('too old'); // slow user
		}
	}
	else {
		#TODO: go back from 30 to 3 hours when it works fine and spammers find us
		$max_oldness = time() - (60*60*30); // thirty hours
		if ($this->sent_at < $max_oldness) {
			throw new Exception('too old'); // slow user
		}
	}
	
	// check for session connection
	if ($this->session_id) {
		try {
			$session = load::model('session');
			if ($this->session_id != $session->id) {
				throw new Exception('wrong session'); // hack
			}
		}
		catch (Exception $e) {
			// session id not in our session table anymore, strange..
			// old session: since then loggedout, deleted, etc.
			// no session: this user doesn't have a session while the form did, hack
			throw new Exception('no session', 0);
		}
	}
	
	if ($this->status != 'ajax') {
		$this->status = 'done';
	}
}

}
?>