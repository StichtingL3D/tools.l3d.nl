<?php
/*------------------------------------------------------------------------------
	user - help to create passwords and unique codes
------------------------------------------------------------------------------*/

class user {

/*------------------------------------------------------------------------------
	passwords & hashing
	
	passphrases based on the answer from 'The Dog' on StackOverflow:
	http://stackoverflow.com/questions/5108248/how-to-generate-random-meaningless-but-at-the-same-time-easy-to-remember-words/5108453#5108453
	
	hasing based on the principles of 'The Wicked Flea's answer on StackOverflow:
	http://stackoverflow.com/questions/401656/secure-hash-and-salt-for-php-passwords/401684#401684
	
	using a site specific salt and a unique/random salt per user password
------------------------------------------------------------------------------*/
public static function hash_password($password, $user_salt) {
	$config = load::config('users');
	$app_salt = $config['password_salt'];
	
	$password_hash = hash_hmac('sha512', $password.$user_salt, $app_salt);
	return $password_hash;
}

public static function create_password_salt() {
	$nonce_salt = mt_rand();
	$nonce_salt .= microtime();
	$nonce_salt .= $_SERVER['REMOTE_ADDR'];
	$user_salt = sha1($nonce_salt);
	
	return $user_salt;
}

public static function generate_unique_id() {
	$nonce = mt_rand();
	$nonce .= microtime();
	$nonce .= $_SERVER['REMOTE_ADDR'];
	$nonce = sha1($nonce);

	return $nonce;
}

public static function generate_passphrase($words=3) {
	$passphrase = '';
	for ($i=0; $i<$words; $i++) {
		$passphrase .= self::generate_passphrase_word().' ';
	}
	
	return rtrim($passphrase);
}

private static function generate_passphrase_word() {
	// letters to use, skip ones that cause confusion in reading or pronounciation
	$consonants = array('b', 'd', 'f', 'g', 'h', 'j', 'k', 'm', 'n', 'p', 'r', 's', 't', 'v', 'w', 'z');
	$vowels = array('a', 'e', 'o', 'u');
	
	$word = '';
	$word_length = rand(4, 6);
	$consonant_toggle = true;
	
	while (strlen($word) < $word_length) {
		if ($consonant_toggle) {
			$word .= $consonants[array_rand($consonants)];
			$consonant_toggle = false;
		}
		else {
			$word .= $vowels[array_rand($vowels)];
			$consonant_toggle = true;
		}
	}
	
	return $word;
}

}
?>