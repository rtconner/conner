<?php

namespace web;

use ConnerException;
use cache;
use URI;
use Setting;

/**
 * Throw a 404
 */
function do_404($type='html') {
	switch($type) {
		case 'text':
			header('Content-Type: text/plain; charset='.strtolower(Setting::get('encoding')));
			break;
		case 'xml':
  			header('Content-Type: text/xml; charset='.strtolower(Setting::get('encoding')));
			break;
		case 'json':
			header('Content-Type: application/json; charset='.strtolower(Setting::get('encoding')));
			break;
		case 'html':
		default:
  			header('Content-Type: text/html; charset='.strtolower(Setting::get('encoding')));
			break;
	}

	header("HTTP/1.0 404 Not Found");

	if($type == 'html') {
		echo elem('404');
	}

	die;
}

/**
 * Include and run an element and it's loader
 * @param $path string
 * @param $params variables to pass into element
 * @param $options
 *   pass [false]|true parent elements pass any parameters to children elements
 *   layout string|[false] use a layout file for this element
 */
function elem($path, $params=array(), $options=array()) {
	if(!is_array($path)) {	$path = explode('/', $path); }

	$ret = false;
	$options = array_merge(array(
		'layout'=>false,
		'cache'=>false,
		'cacheKey'=>'',
	), $options);

	if($options['cache']) {
		$cacheKey = 'Elements.'.KEY.'.'.implode(DS,$path).'.'.$options['cacheKey'];
		$ret = cache\file($cacheKey, null, (int) $options['cache']);
	}

	$elemFile = ELEMENTS.DS.implode(DS,$path).'.ctp';

	if(file_exists($elemFile)) {

		$loadData = array();

		global $WEB_ELEM_LEVEL, $WEB_ELEM_PARAMS;
		if(empty($WEB_ELEM_LEVEL)) {
			$WEB_ELEM_LEVEL = 0;
		}
		if(empty($WEB_ELEM_PARAMS)) {
			$WEB_ELEM_PARAMS = array($WEB_ELEM_LEVEL=>array());
		}
		$WEB_ELEM_LEVEL++;

		try {
			$params = elem_loader($path, $params);
		} catch (ConnerException $e) {

		}

		if(array_key_exists($WEB_ELEM_LEVEL-1, $WEB_ELEM_PARAMS)) {
			$params = array_merge($WEB_ELEM_PARAMS[$WEB_ELEM_LEVEL-1], $params);
		}

		if(!empty($options['pass'])) {
			$WEB_ELEM_PARAMS[$WEB_ELEM_LEVEL] = $params;
		}

		global $WEB_GLOBAL_PARAMS;
		if(!empty($WEB_GLOBAL_PARAMS)) {
			$params = array_merge($WEB_GLOBAL_PARAMS, $params);
		}
		
        if(empty($options['layout'])) {
        	ob_start();
    		elem_file($elemFile, $params);
        	$ret = ob_get_clean();
        } else {
        	ob_start();
    		elem_file($elemFile, $params);
        	$ret = elem_layout($options['layout'], ob_get_clean(), $params);
        }

		$WEB_ELEM_PARAMS[$WEB_ELEM_LEVEL] = array();
	    $WEB_ELEM_LEVEL--;
    } else {
    	throw new ConnerException('Element "'.$elemFile.' Not Found');
    }

	if($options['cache']) {
		cache\file($cacheKey, $ret, (int) $options['cache']);
	}

    return $ret;
}

/**
 * Runs the element loader file
 */
function elem_loader() {
	$__params = null;
	$__path = func_get_arg(0);
	if(func_num_args() > 1) {
		$__params = func_get_arg(1);
	}
	if(!is_array($__path)) { $__path = explode('/', $__path); }

	if(is_array($__params)) {
	    extract($__params);
	}

	$__loadFile = LOADERS.DS.implode(DS, $__path).'.php';

    if(file_exists($__loadFile)) {
		$data = include $__loadFile;

		if(is_array($data)) {
			$__params = array_merge($__params, $data);
		}
    } else {
// 		throw new ConnerException('Loader Not Found : '.$__loadFile);
    }

    return $__params;
}

/**
 * Run an element file
 */
function elem_file($___file, $___params) {
	if(is_array($___params)) {
	    extract($___params);
	}

	if(!file_exists($___file)) {
    	throw new ConnerException('Element File "'.$___file.'" Does Not Exist');
	}

	return include $___file;
}

/**
 * Wrap a block of html with a layout
 */
function elem_layout($name, $content, $params) {
	if(!is_array($params)) {
		$params = array();
	}

	global $WEB_GLOBAL_PARAMS;
	if(!empty($WEB_GLOBAL_PARAMS)) {
		$params = array_merge($WEB_GLOBAL_PARAMS, $params);
	}

	$file = LAYOUTS.DS.$name.'.ctp';

	if(!file_exists($file)) {
    	throw new ConnerException('Layout "'.$file.'" Does Not Exist');
	}

	ob_start();

	$params['content_for_layout'] = $content;
	elem_file($file, $params);

	return ob_get_clean();
}

/**
 * Force the browser to use SSL
 */
function force_ssl() {
	$https = Setting::get('https');
	
	if(@$_SERVER['HTTPS'] != "on" && $https != 'http://'.$_SERVER['HTTP_HOST']) {
		redirect($https.URI::$string);
	}
}

/**
 * @param $message string
 * @param $url string result of a url() call, true to redirect to current url
 * @param $type string|bool 'success', 'error' or other string to use in the flash type
 */
function flash($message=null, $url=null, $type=false) {
	if(is_null($message)) {
		$ret = @$_SESSION['CONNER_FLASH'];
		unset($_SESSION['CONNER_FLASH']);
		return $ret;
	}

	if(is_bool($type)) {
		if($message === true) {
			$message = $type ? __('Error when saving data') : __('Data has been saved');
		}
		$type = $type ? 'error' : 'success';
	}

	if($url === true) {
		$url = url();
	}

	$_SESSION['CONNER_FLASH'] = array('message'=>$message, 'type'=>$type);

	if($url !== null && $url !== false) {
		redirect($url);
	}
}

/**
 * Check if the user agent is a bot
 */
function is_bot(){
	if(IS_CLI || !isset($_SERVER['HTTP_USER_AGENT'])) {
		return false;
	}
	$agent = $_SERVER['HTTP_USER_AGENT'];

	if(preg_match('/bot|crawl|slurp|spider/i', $agent)) {
		return true;
	}

	$botlist = array("Teoma", "alexa", "froogle", "Gigabot", "inktomi",
		"looksmart", "URL_Spider_SQL", "Firefly", "NationalDirectory",
		"Ask Jeeves", "TECNOSEEK", "InfoSeek", "WebFindBot", "girafabot",
		"crawler", "www.galaxy.com", "Googlebot", "Scooter", "Slurp",
		"msnbot", "appie", "FAST", "WebBug", "Spade", "ZyBorg", "rabaz",
		"Baiduspider", "Feedfetcher-Google", "TechnoratiSnoop", "Rankivabot",
		"Mediapartners-Google", "Sogou web spider", "WebAlta Crawler","TweetmemeBot",
		"Butterfly","Twitturls","Me.dium","Twiceler");

	foreach($botlist as $bot){
		if(strpos($agent, $bot)!==false)
		return true;	// Is a bot
	}

	return false;	// Not a bot
}

/**
 * Send redirection header to browser. This does not "auto-route" URL for you,
 * likely you will always want to use web\redirect(web\url('route'));
 */
function redirect($url) {
	if(function_exists('session_write_close')) {
		session_write_close();
	}

	if(empty($url)) {
		$url = '/';
	}

	header('Location: '.$url);
	exit();
}

/**
 * Is user trying to save data? Also can auto trim GET/POST data
 * @param $options array
 *   trim [true]|false trim all post fields
 *   action string if set, only return true if '_action' is this string
 * @return bool
 */
function saving($options=array()) {
	if(empty($_POST)) { return false; }

	$saving = (!empty($_POST) || !empty($_FILES));

	$options = array_merge(array(
		'trim'=>true,
	), $options);

	// todo: check $_SERVER['HTTP_REFERER'] is same domain as self;

	if(array_key_exists('action', $options)) {
		$action = get_var('_action', null);
		unset($_POST['_action']);
		unset($_GET['_action']);

		$saving = $saving && ($action == $options['action']);
	}

	if($options['trim']) {
		array_walk_recursive($_POST, 'trim');
	}

	if(!empty($_FILES)) {
		foreach($_FILES as $name => $data) {
			$_POST[$name] = $data;
		}
	}

	return $saving;
}

/**
 * Set a variable available for user on all layouts and elements
 */
function set($key, $value) {
	global $WEB_GLOBAL_PARAMS;
	$WEB_GLOBAL_PARAMS[$key] = $value;
}

/**
 * Return url string
 * Examples
 *  url('/')
 *  url('/cars');
 *  url('/cars/view', 234);
 *  url('edit', 234);
 *  url('/img/test.gif');
 * @return string URL
 */
function url() {
	$args = func_get_args();

	if(empty($args)) {
		$args = array(URI::$string);
	}

	$base = $args[0];

	$location = strpos($base, '/');

	if($location===0) {
		if($base == '/') {
			$path = '/';
		} else {
			$path = implode('/', $args);
		}
	} else {
		$uriParams = explode('/', URI::$string);
		$path = '/'.$uriParams[1].'/'.implode('/', $args);
	}
	
	return $path;
}

/**
 * Full url with domain
 */
function urlf() {
	$args = func_get_args();
	return trim(Setting::get('http').call_user_func_array('url', $args), '/');
}

/**
 * Full SSL URL
 */
function urls() {
	$args = func_get_args();
	return trim(Setting::get('https').call_user_func_array('url', $args), '/');
}

/**
 * Indiscriminantly get a variable passed from browser
 * by a cookie, or post, or get (in that order)
 * @author Robert Conner <rtconner@gmail.com>
 */
function vars($name=null, $default=null) {
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
 * Sends a header with an error string, meant to be used in ajax functionality
 */
function ajax_error($errorStr) {
	header("HTTP/1.0 418 ".$errorStr);
	exit;
}

/**
 * Is this an ajax request
 */
function is_ajax() {
	if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
		return true;
	}
}