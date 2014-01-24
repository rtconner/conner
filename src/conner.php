<?php
/**
 * Conner PHP Framework
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright     2013 Robert Conner <rtconner@gmail.com>
 * @license       MIT License (http://www.opensource.org/licenses/mit-license.php)
 */

/**
 * It is not intended that you edit this file. Please change settings in etc folder config.php or local.php or bootstrap.php
 */
define('CONNER_START_MEMORY', memory_get_usage());
define('CONNER_START_MICROTIME', (float)substr(microtime(), 0, 10));
define('CONNER_ROOT', realpath(dirname(dirname(__FILE__))));
define('CONNER_WWW', realpath(dirname($_SERVER["SCRIPT_FILENAME"])));

$IS_CLI = (PHP_SAPI === 'cli');
define('IS_CLI', $IS_CLI);

mb_internal_encoding( 'UTF-8' );

define('DS', DIRECTORY_SEPARATOR);
define('SP', ' ');

if(!defined('KEY')) {
	define('KEY', basename(dirname(dirname($_SERVER["SCRIPT_FILENAME"]))));
}

define('ETC', dirname(dirname(dirname($_SERVER["SCRIPT_FILENAME"]))).DS.'etc');

define('MINUTE', 60);
define('HOUR', 3600);
define('DAY', 86400);
define('WEEK', 604800);
define('MONTH', 2592000);
define('YEAR', 31536000);

define('BUILD_NUMBER', file_exists(ETC.DS.'BUILD')?file_get_contents(ETC.DS.'BUILD'):time());

/**
 * Future I18N Implementation
 */
function __($str) { return $str; } // future i18n

/**
 * Shows current memory and execution time of the application.
 *
 * @access public
 * @return array
 */
function benchmark() {
	$current_mem_usage = memory_get_usage();
	$execution_time = microtime() - CONNER_START_MICROTIME;
	if($execution_time < 0) { $execution_time = 'Unknown'; }

	debug(array(
		'current_memory' => round($current_mem_usage/1048576, 4)." MB",
		'start_memory' => round(CONNER_START_MEMORY/1048576, 4)." MB",
		'peak_memory' => round(memory_get_peak_usage()/1048576, 4)." MB",
		'execution_time' => $execution_time,
		'build_number' => BUILD_NUMBER,
	));
}

/**
 * Print out information for debugging
 */
function debug($str, $showLine=true, $escape=true) {
	Setting::get('debug');
	if(true) {
		$isAjax = IS_CLI || web\is_ajax();

		if(!is_bool($showLine) && func_num_args() > 1) {
			foreach(func_get_args() as $arg) {
				debug($arg);
			}
			return;
		}

		if(is_bool($str)) {
			$str = '<i>'.($str?'TRUE':'FALSE').'</i>';
		} elseif(is_null($str)) {
			$str = '<i>'.'NULL'.'</i>';
		} elseif(is_array($str) || is_object($str)) {
			$str = print_r($str, true);
		} elseif(is_string($str) && $escape) {
			$str = h($str);
		}

		$calledFrom = debug_backtrace();
		if($isAjax) {
			$wrapper = "%s (line %d)\n  %s\n\n";
		} else {
			$wrapper = '<div class="developer-debug" style="min-width:100%%;background-color:yellow; padding:4px;text-align:left;font-family:Courier New;margin-bottom:1px;">'.
				'<strong>%s</strong> (line <strong>%d</strong>)'.
				'<pre style="margin:0;"">%s</pre></div>';
		}

		echo sprintf($wrapper,
			substr(str_replace(ROOT, '', $calledFrom[0]['file']), 1),
			$calledFrom[0]['line'],
			trim($str)
		);
	}
}

/**
 * Clear memory used by given models
 */
function destroy_models() {
	$names = func_get_args();

	global $MODELS;
	if(is_array($MODELS)) {
		if(empty($names)) {
			foreach($MODELS as $name => $obj) {
				unset($obj);
				unset($MODELS[$name]);
			}
		} else {
			foreach($names as $name) {
				unset($MODELS[$name]);
			}
		}
	}
}

/**
 * Shortcut access to web\elem
 */
function elem($path, $params=array(), $opts=array()) {
	return web\elem($path, $params, $opts);
}

/**
 * HTML Special Chars
 */
function h($text, $ENT_QUOTES=ENT_QUOTES, $charset=null) {
	if (is_array($text)) {
		return array_map('h', $text);
	}

	if(is_null($charset)) {
		$charset = Setting::get('encoding');
	}

	return htmlspecialchars($text, $ENT_QUOTES, strtoupper($charset));
}

/**
 * Return ip address of current browser client
 */
function ip_address() {
	global $CONNER_IP_ADDRESS;
	if($CONNER_IP_ADDRESS) {
		return $CONNER_IP_ADDRESS;
	}

	if(IS_CLI) {
		return '127.0.0.1';
	} else {
		return $_SERVER['REMOTE_ADDR'];
	}
}

/**
 * Include a library file
 */
function lib($name) {
	$name = str_replace('/', DS, $name);

	if(file_exists(LIB.DS.$name.'.php')) {
		require_once(LIB.DS.$name.'.php');
	} elseif(file_exists(LIB.DS.$name.DS.$name.'.php')) {
		require_once(LIB.DS.$name.DS.$name.'.php');
	} elseif(file_exists(CONNER_ROOT.DS.'src'.DS.'lib'.DS.$name.'.php')) {
		require_once(CONNER_ROOT.DS.'src'.DS.'lib'.DS.$name.'.php');
	} elseif(file_exists(LIB.DS.$name.'.phar')) {
		require_once(LIB.DS.$name.'.phar');
	} else {
// 		throw new Exception('Count Not Find Library "'.$name.'"');
	}
}

/**
 * Append text to logs in TMP/log folder
 */
function log_file($string, $logFile='default') {
	lib('Inflector');
	if(!strlen($logFile)) {
		throw new ConnerException('Invalid Logfile "'.$tmp.'.log"');
	}

	$log_file = TMP.DS.'log'.DS.(Inflector::slug($logFile)).'.log';

	$log = new Monolog\Logger(KEY);
	$log->pushHandler(new Monolog\Handler\StreamHandler($log_file, Monolog\Logger::DEBUG));
	$log->addInfo($string);
}

/**
 * Return instance of Model
 *
 * @param $name string name of the model
 * @param $flavour param of model constructor. Two models of same name and different flavours will be cached in memory separately.
 */
function m($name, $flavour=null) {
	global $MODELS;
	if(!is_array($MODELS)) {
		$MODELS = array();  // consider using SplObjectStorage if it's faster
	}

	$flavourIndex = abs(crc32(serialize($flavour))); // i hope no freak of nature collisions ever happen

	$explosion = explode('\\', $name);
	if(count($explosion) == 2) {
		$prefix = $explosion[0].DS;
		$className = 'model\\'.$explosion[0].'\\'.$explosion[1];
		$fileName = $explosion[1];
	} else {
		$prefix = '';
		$fileName = $name;
		$className = 'model\\'.$name;
	}

	if(array_key_exists($name, $MODELS) && array_key_exists($flavourIndex, $MODELS[$name])) {
		return $MODELS[$name][$flavourIndex];
	}

	if(file_exists(MODELS.DS.$prefix.$fileName.'.php')) {
		require_once(MODELS.DS.$prefix.$fileName.'.php');
		$MODELS[$name][$flavourIndex] = new $className($flavour);
		return $MODELS[$name][$flavourIndex];
	} elseif(file_exists(CONNER_ROOT.DS.'src'.DS.'lib'.DS.'model'.DS.$fileName.'.php')) {
		require_once(CONNER_ROOT.DS.'src'.DS.'lib'.DS.'model'.DS.$fileName.'.php');
		$MODELS[$name][$flavourIndex] = new $className($flavour);
		return $MODELS[$name][$flavourIndex];
	} else {
		throw new ConnerException($name.' Model Does Not Exist');
	}
}

/**
 * Execute a script (from the scripts folder) as a forked background process. Don't allow user
 * input here, there are no security protections.
 * It's called as follows script_exec('cron.php daily') would be :
 * > php cron.php daily
 *
 * @param $command string filename.php
 */
function script_exec($command) {
	$path = ROOT.DS.'scripts';

	chdir($path);
	if(substr(php_uname(), 0, 7) == "Windows"){
		pclose(popen("start /b php $command", "r"));
	} else {
		exec("php $command > /dev/null &");
	}
}

/**
 * Utility class for handling app settings
 *
 * Setting::get('String')
 * Setting::set('String', $val)
 * Setting::isset('String')
 * Setting::unset('String')
 */
class Setting {

	private static $_properties = array();

	public static function __callStatic($method, $args) {
		$property = $args[0];

		switch($method) {
			case 'set':
				self::$_properties[ $property ] = $args[1];
				break;
			case 'get':
				if(array_key_exists($property, self::$_properties)) {
					return self::$_properties[$property];
				} else {
					return null;
				}
				break;
			case 'isset':
				return isset(self::$_properties[$property]);
				break;
			case 'unset':
				unset(self::$_properties[$property]);
				break;
			default:
				break;
        }
	}
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
	return call_user_func_array('web\url', $args);
}

if(file_exists(ETC.DS.'config.php')) {
	require(ETC.DS.'config.php');
}

if(file_exists(ETC.DS.'config.'.KEY.'.php')) {
	require(ETC.DS.'config.'.KEY.'.php');
}

if (!defined('ROOT')) {
	define('ROOT', dirname(dirname(dirname($_SERVER["SCRIPT_FILENAME"]))));
}

/**
 * Prefix will be used for elements, loaders, and layouts folders
 */
if (!defined('PREFIX'))
	define('PREFIX', false);

$prefix = PREFIX
	? (is_bool(PREFIX) ? DS.KEY : DS.PREFIX)
	: '';

if(!defined('ELEMENTS'))
	define('ELEMENTS', ROOT.DS.KEY.DS.'elements'.$prefix);

if(!defined('LAYOUTS'))
	define('LAYOUTS', ROOT.DS.KEY.DS.'layouts'.$prefix);

if(!defined('LIB'))
	define('LIB', ROOT.DS.'lib');

if(!defined('LOADERS'))
	define('LOADERS', ROOT.DS.KEY.DS.'loaders'.$prefix);

if(!defined('EHANDLERS'))
	define('EHANDLERS', ROOT.DS.KEY.DS.'ehandlers'.$prefix);

if(!defined('MODELS'))
	define('MODELS', ROOT.DS.'models');

if($IS_CLI) {
	ignore_user_abort(true);
	set_time_limit(0);
	require('console.php');
} else {
	
	session_start();
	require('web.php');
	
	if(!Setting::get('http')) {
		Setting::set('http', 'http://'.$_SERVER['HTTP_HOST']);
	}
	
	if(!Setting::get('https')) {
		Setting::set('https', Setting::get('http'));
	}
	
}

require('exceptions.php');

if(file_exists(ETC.DS.'local.'.KEY.'.php')) {
	require(ETC.DS.'local.'.KEY.'.php');
}

if(file_exists(ETC.DS.'local.php')) {
	require(ETC.DS.'local.php');
}

if(!defined('TMP'))
	define('TMP', ROOT.DS.'tmp');

if (!defined('CACHE'))
	define('CACHE', ROOT.DS.'tmp'.DS.'cache');

lib('cache');

if(Setting::get('debug')) {
	error_reporting(E_ALL);
	ini_set('display_errors','On');

	function exception_handler($exception) {
		$class = get_class($exception);
  		$out = "<b>Uncaught $class:</b> ".$exception->getMessage().
			'<b> in '.$exception->getFile().' on line '.$exception->getLine().'</b>';

		ob_start();
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $trace = $exception->getTraceAsString();
        $trace = preg_replace ('/^#0\s+' . __FUNCTION__ . "[^\n]*\n/", '', $trace, 1);
        ob_end_clean();

        debug($out."\n".$trace, false, false);
	}

	function error_handler($errno, $errstr, $errfile, $errline) {

		if(!($errno & error_reporting())) {
			return;
		}

		switch($errno) {
			case E_ERROR: // 1 //
				$errName = 'E_ERROR';break;
			case E_WARNING: // 2 //
				$errName = 'E_WARNING';break;
			case E_PARSE: // 4 //
				$errName = 'E_PARSE';break;
			case E_NOTICE: // 8 //
				$errName = 'E_NOTICE';break;
			case E_CORE_ERROR: // 16 //
				$errName = 'E_CORE_ERROR';break;
			case E_CORE_WARNING: // 32 //
				$errName = 'E_CORE_WARNING';break;
			case E_CORE_ERROR: // 64 //
				$errName = 'E_COMPILE_ERROR';break;
			case E_CORE_WARNING: // 128 //
				$errName = 'E_COMPILE_WARNING';break;
			case E_USER_ERROR: // 256 //
				$errName = 'E_USER_ERROR';break;
			case E_USER_WARNING: // 512 //
				$errName = 'E_USER_WARNING';break;
			case E_USER_NOTICE: // 1024 //
				$errName = 'E_USER_NOTICE';break;
			case E_STRICT: // 2048 //
				$errName = 'E_STRICT';break;
			case E_RECOVERABLE_ERROR: // 4096 //
				$errName = 'E_RECOVERABLE_ERROR';break;
			case E_DEPRECATED: // 8192 //
				$errName = 'E_DEPRECATED';break;
			case E_USER_DEPRECATED: // 16384 //
				$errName = 'E_USER_DEPRECATED';break;
		}

		$e = new ErrorException($errName.' '.$errstr, 0, $errno, $errfile, $errline);
		exception_handler($e);
		return;
    }

	set_exception_handler('exception_handler');
	set_error_handler('error_handler');
} else {
	error_reporting(0);
	ini_set('display_errors', 'Off');

	function exception_handler($exception) {
		$pieces = explode('/', trim(URI::$string, '/'));
		if(file_exists(EHANDLERS.DS.implode(DS, $pieces).'.php')) {
			$message = $exception->getMessage();
			if(empty($message)) { $message = 'There was an unkown error'; }
			include(EHANDLERS.DS.implode(DS, $pieces).'.php');
			exit;
		}

		web\do_404();
	}

	function error_handler($errno, $errstr, $errfile, $errline) {
		$pieces = explode('/', trim(URI::$string, '/'));
		if(file_exists(EHANDLERS.DS.implode(DS, $pieces).'.php')) {
			$message = $errstr;
			if(empty($message)) { $message = 'There was an unkown error'; };
			include(EHANDLERS.DS.implode(DS, $pieces).'.php');
			exit;
		}

		web\do_404();
	}
}

if(file_exists(ETC.DS.'bootstrap.php')) {
	require(ETC.DS.'bootstrap.php');
}

if(empty($_GET['uri'])) {
	abstract class URI {
		public static $string = '/';
		public static $array = array('/');
		public function __toString () {
			return self::$string;
		}
	}
} else {
	// i dont love this solution .. need better
	abstract class URI {
		public static $string;
		public static $array;
		public static function init() {
			self::$string = $_GET['uri'];
			self::$array = explode('/', trim($_GET['uri'], '/'));
		}
		public function __toString () {
			return self::$string;
		}
	}
	URI::init();
}

require('util.php');