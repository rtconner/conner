<?php
namespace auth;

/**
 * Is user currently logged in, return user_id of current user
 */
function user_id() {
	global $AUTH_USER_ID;
	if(!empty($AUTH_USER_ID)) {
		return $AUTH_USER_ID;
	}

	if(IS_CLI) {
		return 0;
	}

	if(array_key_exists('USER_ID', $_SESSION)) {
		return $_SESSION['USER_ID'];
	}

	if(array_key_exists('USER_AUTH', $_COOKIE)) {
		if($userId = m('user')->find('cookie_auth', $_COOKIE['USER_AUTH'])) {
			$_SESSION['USER_ID'] = $userId;
			return $_SESSION['USER_ID'];
		}
	}

	unset($_SESSION[@'USER_ID']);
	return false;
}

/**
 * Non-session based login using the api key
 */
function apikey_login($apiKey) {
	global $AUTH_USER_ID;

	$userId = (string) m('userApi')->userId($apiKey);

	if(strlen($userId)) {
		$AUTH_USER_ID = $userId;
		return true;
	}

	return false;
}

/**
 * Check $_POST data and log user in
 */
function login($data) {
	if(mb_strlen($data['username']) && mb_strlen($data['password'])) {

		$user = m('user')->load($data['username']);
		if(empty($user)) { return false; }
		
		if($user['password'] === m('user')->password_hash($data['password'])) {

			global $AUTH_USER_ID;
			$AUTH_USER_ID = $_SESSION['USER_ID'] = $data['username'];

			$cookieAuth = '';
			if(!empty($data['remember'])) {
				$cookieAuth = hash('ripemd160', $user['password'].uniqid());
				setcookie('USER_AUTH', $cookieAuth, time()+YEAR);
			}

			m('user')->saveData($data['username'], array(
				'last_login'=>time(),
				'last_login_ip'=>$_SERVER['REMOTE_ADDR'],
				'cookie_auth'=>$cookieAuth,
			));

			return true;
		}
	}
	return false;
}

/**
 * Log user out
 */
function logout() {
	global $AUTH_USER_ID;
	$AUTH_USER_ID = null;
	unset($_SESSION['USER_ID']);
	setcookie('USER_AUTH', false);
}

/**
 * Return user data stored in session
 * TODO: cache/speed
 */
function user($key=null) {
	if($user_id = user_id()) {
		$user = m('user')->load($user_id);
		if($key !== null) {
			return @$user[$key];
		}
		return $user;
	}
	return null;
}