<?php
/*------------------------------------------------------------------------------
	email - sending email from our own system
------------------------------------------------------------------------------*/

class email {
	
private static $from_email;
private static $from_name;

private static $swift_loaded = false;
private static $swift;

private static $attachment_cache;

/*------------------------------------------------------------------------------
	check for a valid email address
------------------------------------------------------------------------------*/
public static function check_emailaddress($emailaddress) {
	// Swift_Validate doesn't work, instead, add an emailaddres to a draft
	
	// prepare swift
	try {
		self::load_swiftmailer();
		$message = Swift_Message::newInstance('test');
	}
	catch (Exception $e) {
		throw new Exception('test-error', 0, $e);
	}
	
	// actual test
	try {
		$message->setTo($emailaddress);
	}
	catch (Swift_RfcComplianceException $e) {
		throw new Exception('invalid', 0, $e);
	}
	
	unset($message);
	return true;
}

/*------------------------------------------------------------------------------
	sending email
	
	$to = (string)email OR array(email => name) OR array(email => name, ..)
	$subject & $body = (string) (no wordwrap needed, as that is done by swift)
	$attach_info = array(path => string, mime => string)
	$other_recipients = the same as $to used as cc
		OR array(cc = the same as $to, bcc.., reply-to..)
------------------------------------------------------------------------------*/
public static function send($to, $subject, $body, $attach_info=false, $other_recipients=false) {
	// modify the subject for non production
	if (ENVIRONMENT != 'production') {
		$to = self::create_development_address($to);
		$other_recipients = self::create_development_address($other_recipients);
		
		$subject = '['.ENVIRONMENT.'] '.$subject;
	}
	
	// convert keywords to actual email addresses
	$to = self::convert_address($to);
	$other_recipients = self::convert_address($other_recipients);
	
	try {
		self::load_swiftmailer();
		
		// start composing the email
		$message = Swift_Message::newInstance($subject);
		
		// add emailaddresses
		try {
			$message->setFrom(array(self::$from_email => self::$from_name));
			$message->setTo($to);
			
			// send to other recipients as well
			if (is_array($other_recipients)) {
				// cc
				if (isset($other_recipients['cc'])) {
					$message->setCc($other_recipients['cc']);
					unset($other_recipients['cc']);
				}
				// bcc
				if (isset($other_recipients['bcc'])) {
					$message->setBcc($other_recipients['bcc']);
					unset($other_recipients['bcc']);
				}
				// reply-to
				if (isset($other_recipients['reply-to'])) {
					$message->setReplyTo($other_recipients['reply-to']);
					unset($other_recipients['reply-to']);
				}
				// default to cc for the rest (done as array(email=>name))
				if (!empty($other_recipients)) {
					$message->setCc($other_recipients);
				}
			}
			// default to cc for the rest (done as string 'email')
			elseif (!empty($other_recipients)) {
				$message->setCc($other_recipients);
			}
		}
		catch (Swift_RfcComplianceException $e) {
			throw new Exception('non valid emailaddress', 0, $e);
		}
		
		// add the email body
		$message->setBody($body);
		
		// adding attachments
		if ($attach_info) {
			$message->attach(self::add_attachment($attach_info));
		}
		
		// send
		return self::$swift->send($message);
	}
	catch (Exception $e) {
		throw new Exception('error sending mail: '.$e->getMessage(), 0, $e);
	}
}

// turn special keywords into real email addresses
// so i.e.: array('ambassador' => $id_of_photographer)
// becomes: array('ambassadorname@citysessies.nl' => 'ambassador full name')
private static function convert_address($address) {
	// loop over groups of addresses
	if (is_array($address) && is_array(current($address))) {
		foreach ($address as &$sub) {
			$sub = self::convert_address($sub);
		}
		return $address;
	}
	
	/*--- convert special keywords ---*/
	
	// the ambassador of a specific photographer
	if (is_array($address) && key($address) == 'ambassador' && is_int(current($address))) {
		// get the photographer we want the ambassador of
		$photographer_id = current($address);
		$photographer = load::model('photographer', $photographer_id);
		
		// get the ambassador of this photographer
		$ambassador_id = $photographer->ambassador_id;
		$ambassador = load::model('photographer', $ambassador_id);
		
		// get the email address of the ambassador
		$user_id = $ambassador->user_id;
		$user = load::model('user', $user_id);
		
		return array($user->emailaddress => $ambassador->full_name);
	}
	
	// plain return everything else
	return $address;
}

private static function create_development_address($email) {
	if (is_string($email)) {
		$email_is_array = false;
		$email_address = $email;
	}
	elseif (is_array($email) && count($email) === 1 && strpos(key($email), '@')) {
		$email_is_array = true;
		$email_address = key($email);
	}
	else {
		// return unchanged, we can't help you
		return $email;
	}
	
	// remove some if this is a citysessies domain
	$email_address = str_replace('@citysessies.nl', '', $email_address);
	
	// create a key from the emailadress, to be able to use it as part of another emailaddress
	$email_address_key = str_replace(array('@', '.', '+'), '_', $email_address);
	
	// attach the key as a subaddress of the webmaster
	$email_address_changed = str_replace('@', '+'.$email_address_key.'@', WEBMASTER);
	
	// create an array again, if needed
	$email = ($email_is_array) ? array($email_address_changed => current($email)) : $email_address_changed;
	
	return $email;
}

private static function add_attachment($info) {
	if (isset(self::$attachment_cache[$info['path']])) {
		$attachment = self::$attachment_cache[$info['path']];
	}
	else {
		$attachment = Swift_Attachment::fromPath($info['path'], $info['mime']);
		self::$attachment_cache[$info['path']] = $attachment;
	}
	
	return $attachment;
}

/*------------------------------------------------------------------------------
	starting up swift mailer
------------------------------------------------------------------------------*/
public static function construct() {
	$config = new config('swift');
	self::$from_email = $config['user'];
	self::$from_name = $config['name'];
}

private static function load_swiftmailer() {
	if (self::$swift_loaded) {
		return;
	}
	
	/*--- load swift ---*/
	
	$places = json_decode(PLACES, true);
	$swiftmailer_path = $places['backend'].'includes/Swift/lib/swift_required.php';
	$result = require_once($swiftmailer_path);
	
	if ($result) {
		self::$swift_loaded = true;
	}
	else {
		throw new Exception('failed to load swift mailer');
	}
	
	/*--- start swift ---*/
	
	$config = new config('swift');
	
	$swift_transport = Swift_SmtpTransport::newInstance($config['host'], $config['port'], $config['ssl'])
		->setUsername($config['user'])
		->setPassword(base64_decode($config['pass']))
		;
	
	self::$swift = Swift_Mailer::newInstance($swift_transport);
}

}

email::construct();
?>