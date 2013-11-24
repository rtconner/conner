<?php

/**
 * util.php
 * Collection of various utility functions to extend PHP
 * These methods have no real link to Conner Framework, or reliance on it. Just thing that I think PHP should have added
 * in on its own.
 */

/**
 * CSV escape
 */
function csve($str, $quote=false) {
	if(is_array($str)) {
		$ret = array();
		foreach($str as $s)
			$ret[] = csve($s, $quote);
		return $ret;
	}
	$str = trim($str);
	$str = preg_replace ("/\"/", "\"\"", $str);
	if($quote)
		$str = '"'.$str.'"';
	return $str;
}

/**
 * Follow headers on a given URL and expand it to it's final redirect.
 */
function expand_url($url) {
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	$a = curl_exec($ch);
	if(preg_match('#Location: (.*)#', $a, $r)) {
		$l = trim($r[1]);
		return $l;
	}
	return '';
}

/**
 * Javascript escape
 */
function jse($str) {
	$str = str_replace("\n", "", str_replace("\r", "", $str));
	return addslashes($str);
}


/**
 * Indiscriminantly get a variable passed from browser
 * by a cookie, or post, or get (in that order)
 * @author Robert Conner <rtconner@gmail.com>
 */
function get_var($name=null, $default=null) {
	if(is_null($name)) {
		return array_merge($_GET, $_POST);
	} elseif(isset($_POST[$name]))
	return $_POST[$name];
	elseif(isset($_GET[$name]))
	return $_GET[$name];
	elseif(isset($_COOKIE[$name]))
	return $_COOKIE[$name];
	else
		return $default;
}

/**
 * Strip new line breaks from a string
 */
function strip_nl($str) {
	return str_replace("\n", "", str_replace("\r", "", $str));
}
