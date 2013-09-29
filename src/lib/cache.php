<?php

namespace cache;

use Setting;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

///**
// * Read/write/clear a cache. This function makes calls to cache_file(), cache_memcached, and cache_db()
// * Engines must be pre-configured in the etc config file
// *
// * @param $key unique identifier
// * @param $value any value to set, (null to read, false to delete)
// * @param $protocol string ex: "engine:seconds" (null uses default protocol)
// */
//function cache($key, $value=null, $seconds=HOUR, $engine=null) {
//	if(Setting::get('Cache.disable')) {
//		return null;
//	}
//
//	if(is_null($engine) && defined('CACHE_ENGINE')) {
//		$engine = CACHE_ENGINE;
//	} elseif(is_null($engine)) {
//		$engine = 'file';
//	}
//
//	switch($engine) {
//		case 'file':
//			return cache_file($key, $value, $seconds);
//			break;
//	}
//
//}

/**
 * Cache using file system. Do not use not-alphanum characters a-z0-9.- only
 * This is not case sensitive
 * @param $key cache path separated by period
 * @param $value null to fetch, any other value to set
 */
function file($key, $value=null, $seconds=HOUR) {
	if(Setting::get('Cache.disable')) {
		return null;
	}

	$now = time();
	$key = trim(preg_replace("/[^\w\.-]/", "_", strtolower($key)));
	$keys = array_filter(explode('.', $key));
	$cache_file = CACHE.DS.implode(DS, $keys).'.tmp';

	if(is_null($value)) {
		if(file_exists($cache_file)) {
			$data = unserialize(file_get_contents($cache_file));
			if(array_key_exists(0, $data) && array_key_exists(1, $data) && $data[1] > $now) {
				return $data[0];
			} else {
				unlink($cache_file);
			}
		}
	} else {
		$data = array($value, $now+$seconds);
		@mkdir(dirname($cache_file), 0777, true);
		file_put_contents($cache_file, serialize($data));
	}
	return null;
}

/**
 * Deletes all cache files that match the given or are under it.
 * file_clear('Foo.bar') deletes Foo.bar.1 and Foo.bar
 *
 * @param $key cache key path
 */
function file_clear($key) {
	$path = trim(preg_replace("/[^\w\.-]/", "_", strtolower($key)));
	$path = array_filter(explode('.', $path));
	$fullPath = CACHE.DS.implode(DS, $path);

	@unlink($fullPath.'.tmp');
	try {
		$files = new RecursiveIteratorIterator(
		    new RecursiveDirectoryIterator($fullPath, RecursiveDirectoryIterator::SKIP_DOTS),
		    RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ($files as $fileinfo) {
		    $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
		    $todo($fileinfo->getRealPath());
		}

	} catch(\UnexpectedValueException $e) {}
}

// todo: integrate memcached
function mem($key, $value=null, $seconds=420) { // 7 mins
	return file($key, $value, $seconds);
}
function mem_clear($key) {
	return file_clear($key);
}

/**
 * Use the browser session as a cache engine. There is no delete, because I feel like if you need to delete 
 * session cache, then you should use another type of cache engine.
 * @param $key cache path separated by period
 * @param $value null to fetch, any other value to set
 */
function session($key, $value=null) {
	if(!isset($_SESSION)) { return null; }
	
	$path = trim(preg_replace("/[^\w\.-]/", "_", strtolower($key)));

	if(!is_array(@$_SESSION['CACHE'])) { $_SESSION['CACHE'] = array(); }

	if(is_null($value)) {
		return array_key_exists($path, @$_SESSION['CACHE']) ? @$_SESSION['CACHE'][$path] : null;	
	} else {
		$_SESSION['CACHE'][$path] = $value;	
	}	
}