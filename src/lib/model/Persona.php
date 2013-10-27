<?php
namespace model;

lib('model/Model');

/**
 * Collection of methods for handling using the Mozilla Persona library
 * as an auth system
 * @author rtconner
 */
class Persona extends Model {

	public function login() {

		$url = 'https://verifier.login.persona.org/verify';
		$assert = filter_input(
				INPUT_POST,
				'assertion',
				FILTER_UNSAFE_RAW,
				FILTER_FLAG_STRIP_LOW|FILTER_FLAG_STRIP_HIGH
		);

		//Use the $_POST superglobal array for PHP < 5.2 and write your own filter
		$params = 'assertion=' . urlencode($assert) . '&audience=' . urlencode('http://local.interested');
		$ch = curl_init();
		$options = array(
				CURLOPT_URL => $url,
				CURLOPT_RETURNTRANSFER => TRUE,
				CURLOPT_POST => 2,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_SSL_VERIFYHOST => 2,
				CURLOPT_POSTFIELDS => $params
		);
		curl_setopt_array($ch, $options);
		$json = curl_exec($ch);
		curl_close($ch);

		$data = json_decode($json);

		if($data->status == 'okay') {

			$data->assertion = $assert;
			$_SESSION['PERSONA'] = $data;
			echo $json;
			return true;

		} else {

			$this->logout();
			return false;

		}
	}

	function logout() {
		unset($_SESSION['PERSONA']);
	}

	/**
	 * Return persona data, or null value if missing
	 * @$key optional key within persona data to fetch
	 *
	 * https://developer.mozilla.org/en-US/Persona/Remote_Verification_API?redirectlocale=en-US&redirectslug=Persona%2FRemote_Verification_API#okay
	 */
	function get($key=null) {
		if(array_key_exists('PERSONA', $_SESSION)) {
			if(is_null($key)) {
				return $_SESSION['PERSONA'];
			}
			return @$_SESSION['PERSONA']->{$key};
		}
		return null;
	}

	function email() {
		return $this->get('email');
	}

}
