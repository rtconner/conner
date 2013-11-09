<?php
namespace html;

/**
 * Returns a charset META-tag.
 *
 * @param string $charset The character set to be used in the meta tag. If empty,
 *  The App.encoding value will be used. Example: "utf-8".
 * @return string A meta tag containing the specified character set.
 */
function content_type($charset = null) {
	if (empty($charset)) {
		$charset = ENCODING;
	}
	return sprintf('<meta http-equiv="Content-Type" content="text/html; charset=%s" />', strtolower($charset));
}

/**
 * Build an attributes string for use in an html tag
 */
function build_attributes($attributes) {
	$attrString = '';
	foreach($attributes as $aName => $aVal) {
		$attrString .= ' '.$aName.'="'.h($aVal).'"';
	}
	return $attrString;
}

/**
 * Return CSS link tag
 */
function css($name, $opts=array()) {
	$opts = array_merge(array(
		'media'=>'screen, projection',
	), $opts);

	$path = ($name[0] == '/') ? url($name) : url('/css/'.$name);

	$str = sprintf('<link rel="stylesheet" type="text/css" href="%s" media="%s" />', $path, $opts['media']);

	if(!empty($opts['if'])) {
		$str = '<!--[if '.$opts['if'].']>'.$str.'<![endif]-->';
	}

	return $str;
}

function ending($str='') {

	flash();
	$str .= jsready();
	$str .= jsfoot();

	return $str;
}

function flash($ajax=false) {
	if(!empty($_SESSION['FRAMEWORK_FLASH'])) {
		$message = $_SESSION['FRAMEWORK_FLASH']['message'];
		$type = $_SESSION['FRAMEWORK_FLASH']['type'];
		$js = 'alrt.'.$type.'(\''.jse($message).'\', true)';
		$ajax
			? jsrun($js)
			: jsready($js);
		unset($_SESSION['FRAMEWORK_FLASH']);
	}
}

/**
 * Return html to inlude javascript file
 */
function jsfile($name, $head=false) {

	if(!strncmp($name, 'http', strlen('http'))) {
		$path = $name;
	} else {
		$path = ($name[0] == '/')
			? url($name)
			: url('/js/'.$name);
	}

	if($head) {
		head_append(sprintf('<script type="text/javascript" src="%s"></script>', $path));
	} else {
		return sprintf('<script type="text/javascript" src="%s"></script>', $path);
	}
}

/**
 * Add some javascript code to a document.onReady handler
 */
function jsready($js=null) {
	global $HTML_READY;

	if(mb_strlen($js)) {
		$HTML_READY[] = $js;
	} elseif(is_null($js) && !empty($HTML_READY)) {
		return '<script type="text/javascript">jQuery(document).ready(function(){'.implode(';', $HTML_READY).'});</script>';
	}
	return '';
}

/**
 * Add some javascript code to the header
 */
function jsrun($js=null) {
	global $HTML_RUN;

	if(mb_strlen($js)) {
		$HTML_RUN[] = $js;
	} elseif(is_null($js) && !empty($HTML_RUN)) {
		return sprintf('<script type="text/javascript">%s</script>', implode(';', $HTML_RUN));
	}
	return '';
}

/**
 * Add some javascript code to the footer
 */
function jsfoot($js=null) {
	global $HTML_JSFOOT;

	if(mb_strlen($js)) {
		$HTML_JSFOOT[] = $js;
	} elseif(is_null($js) && !empty($HTML_JSFOOT)) {
		return sprintf('<script type="text/javascript">%s</script>', implode(';', $HTML_JSFOOT));
	}
	return '';
}

function head($head) {
	global $HTML_HEAD;
	if(!headers_sent()) {
  		header('Content-Type: text/html; charset='.strtolower(ENCODING));
  	}

	$jsRun = jsrun();

	return
		'<!DOCTYPE html><html lang="en"><head>'.content_type().'<title>'.title().'</title><link href="/favicon.ico" type="image/x-icon" rel="icon" /><link href="/favicon.ico" type="image/x-icon" rel="shortcut icon" />'.
		$head.
		$jsRun.
		$HTML_HEAD.'</head>';
}

function head_append($str=null) {
	global $HTML_HEAD;
	if(is_null($str)) {
		return (string) $HTML_HEAD;
	}
	$HTML_HEAD .= $str;
}

/**
 * Creates an HTML link.
 * If $url starts with "http://" this is treated as an external link. Else,
 * it is treated as a path to controller/action and parsed with the
 * HtmlHelper::url() method.
 *
 * @param string $title The content to be wrapped by <a> tags.
 * @param mixed $url relative URL or array of URL parameters, or external URL (starts with http://)
 * @return string An `<a />` element.
 */
function link($url, $title, $options=array()) {
	$escapeTitle = array_key_exists('escape', $options) && $options['escape'];

	$options['href'] = url($url);

	return tag('a', $title, $options);
}

function no_cache() {
	if(!headers_sent()) {
		header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-store, no-cache, must-revalidate");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
	}
	return '<meta http-equiv="expires" CONTENT="Fri, 1 Jan 1990 00:00:00 GMT"><meta http-equiv="pragma" CONTENT="no-cache"><meta http-equiv="cache-control" value="no-cache, no store, must-revalidate">';
}

function preload($image) {
	jsfoot('var i = new Image();i.src=\''.url('/img/'.$image).'\'');
}

/**
 * Include a package of js/css into the current document. Works in conjunction with head() method.
 */
function package($name, $opts=array()) {
	global $HTML_PACKAGES;

	if(empty($HTML_PACKAGES[$name])) {
		$ret = '';
		$FOLDER = SELF_PATH.DS.'package'.DS.$name.DS;
		$PATH = '/package/'.$name.'/';

		if(file_exists($FOLDER.$name.'.css'))
			$ret .= css($PATH.$name.'.css', array());
		if(file_exists($FOLDER.'ie.css'))
			$ret .= css($PATH.'ie.css', array('if'=>'lt IE 8'));
		if(file_exists($FOLDER.'ie8.css'))
			$ret .= css($PATH.'ie8.css', array('if'=>'IE 8'));
		if(file_exists($FOLDER.'ie7.css'))
			$ret .= css($PATH.'ie7.css', array('if'=>'IE 7'));
		if(file_exists($FOLDER.'ie6.css'))
			$ret .= css($PATH.'ie6.css', array('if'=>'IE 6'));

		if(file_exists($FOLDER.$name.'.js'))
			$ret .= jsfile($PATH.$name.'.js', false);

		$HTML_PACKAGES[$name] = true;

		if(empty($opts['inline'])) {
			head_append($ret);
		} else {
			return $ret;
		}
	}
}

function title($title=null) {
	global $HTML_TITLE;
	if(is_null($title)) {
		if(empty($HTML_TITLE)) {
			return DEFAULT_TITLE;
		}
		return $HTML_TITLE;
	} else {
		$HTML_TITLE = $title;
	}
}

/**
 * Return formated HTML of generic any tag
 */
function tag($name, $text, $options = array()) {
	if (!is_array($options)) {
		$options = array('class' => $options);
	}

	if (is_array($options) && isset($options['escape']) && $options['escape']) {
		$text = h($text);
	}

	unset($options['escape']);

	$attrs = array();
	$minimized = array('compact', 'checked', 'declare', 'readonly', 'disabled', 'selected', 'defer', 'ismap', 'nohref', 'noshade', 'nowrap', 'multiple', 'noresize');
	foreach($options as $key => $val) {
		$key = strtolower($key);
		if(in_array($key, $minimized) && !empty($val)) {
			$attrs[] = $key;
		} else {
			$attrs[] = sprintf('%s="%s"', $key, $val);
		}
	}
	$attrs = implode(' ', $attrs);

	return sprintf('<%s%s>%s</%s>', $name, mb_strlen($attrs)?' '.$attrs:'', $text, $name);
}

function field_error($str, $show=true) {
	global $HTML_FIELD_ERROR;

	if(empty($HTML_FIELD_ERROR)) {
		jsready('
			$(\'.ein\').live(\'click\', function(e){
				e.stopPropagation();
				$(this).parent().hide();
			});
		');
	}

	$HTML_FIELD_ERROR = true;
	return '<div class="eout" style="display:'.($show?'block':'none').';"><div class="ein">'.$str.'</div>
		<div class="ein10 einb"></div>
		<div class="ein9 einb"></div>
		<div class="ein8 einb"></div>
		<div class="ein7 einb"></div>
		<div class="ein6 einb"></div>
		<div class="ein5 einb"></div>
		<div class="ein4 einb"></div>
		<div class="ein3 einb"></div>
		<div class="ein2 einb"></div>
		<div class="ein1 einb"></div>
	</div>';
}

/**
 * Return html if first value is a non empty string, otherwise return an empty string
 * pass in params same as sprintf
 */
function sprintif($template, $value) {

	if(strlen($value)) {
		return call_user_func_array('sprintf', array($template, h($value)));
	}

	return '';
}