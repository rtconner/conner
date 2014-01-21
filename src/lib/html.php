<?php
namespace html;

use Setting;
use DOMDocument;
use DOMElement;

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
 *
 * @param $name string.css
 * @param $opts
 *   media
 *   if
 *
 * todo: google cachebustin
 * function cachebustin($f) { echo $f . '?=' . date("mdyHis", filemtime($f)); }
 */
function css($name, $opts=array()) {
	$opts = array_merge(array(
		'media'=>'screen, projection',
	), $opts);

	$path = ($name[0] == '/') ? url($name) : url('/css/'.$name);

	$str = sprintf('<link rel="stylesheet" type="text/css" href="%s" media="%s" />', $path.'?'.BUILD_NUMBER, $opts['media']);

	if(!empty($opts['if'])) {
		$str = '<!--[if '.$opts['if'].']>'.$str.'<![endif]-->';
	}

	return $str;
}

function ending($str='') {
	$str .= jsready();
	$str .= jsfoot();

	return $str;
}

function flash($ajax=false) {
	if(!empty($_SESSION['FRAMEWORK_FLASH'])) {
		$message = $_SESSION['FRAMEWORK_FLASH']['message'];
		$type = $_SESSION['FRAMEWORK_FLASH']['type'];
		unset($_SESSION['FRAMEWORK_FLASH']);

		return \web\elem('flash', array('message'=>$message, 'type'=>$type, 'ajax'=>$ajax));
	}

	return '';
}

/**
 * Return html to inlude javascript file
 */
function jsfile($name, $head=false) {
	$path = ($name[0] == '/') ? url($name) : url('/js/'.$name);

	$html = sprintf('<script type="text/javascript" src="%s"></script>', $path.'?'.BUILD_NUMBER);

	if($head) {
		head_append($html);
	} else {
		return $html;
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

/**
 * Build a document <head> tag
 */
function head($head) {
	global $HTML_HEAD;
	if(!headers_sent()) {
  		header('Content-Type: text/html; charset='.strtolower(ENCODING));
  	}

	$jsRun = jsrun();

	return
		'<!DOCTYPE html><html lang="en"><head>'.content_type().'<title>'.h(title()).'</title><link href="/favicon.ico" type="image/x-icon" rel="icon" /><link href="/favicon.ico" type="image/x-icon" rel="shortcut icon" />'.
		$head.
		$jsRun.
		$HTML_HEAD.'</head>';
}

/**
 * Append a chunk of html to the documents <head> tag. Works in conjunction with head() method. 
 * @param $html string (function returns existing head if null)
 */
function head_append($html=null) {
	global $HTML_HEAD;
	if(is_null($html)) {
		return (string) $HTML_HEAD;
	}
	$HTML_HEAD .= $html;
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

/**
 * For use in html head. Handles actions to tell browser not to cache the current document
 */
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

/**
 * Pre-load an image into client browsers memory
 */
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

		$FOLDER = CONNER_WWW.DS.'package'.DS.$name.DS;
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

/**
 * Set string to use in document <title> tag
 */
function title($title=null, $usePrefix=true) {
	global $HTML_TITLE;
	
	if(is_null($title)) {
		if(!strlen($HTML_TITLE)) {
			return Setting::get('Doc.title_default');	
		}
		
		return $HTML_TITLE;
	} else {
		$HTML_TITLE = ($usePrefix?Setting::get('Doc.title_prefix'):'').h($title);
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

function field_error($str, $show=true) {
//	global $HTML_FIELD_ERROR;
//
//	if(empty($HTML_FIELD_ERROR)) {
//		jsready('
//			$(\'.ein\').on(\'click\', function(e){
//				e.stopPropagation();
//				$(this).parent().hide();
//			});
//		');
//	}
//
//	$HTML_FIELD_ERROR = true;
	return '<div class="eout" style="display:'.($show?'block':'none').';"><div class="ein" onclick="this.parentNode.style.display=\'none\';return false;">'.$str.'</div>
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
 * $result = html\to_dom("<html><head><title>teste</title></head><body style='background:red;'>ola <span id='testando'>teste</span> do mundo</body></html>"); // gets a array with id of the first Child and the code of the rest..
 * echo $result["code"]; // to show the code
 * echo "document.body.appendChild(".$result["id"].")"; // To show the result in document.body
 */
function to_dom($html) {
	if(is_string($html)){
		$type = "string";
	} else{
		$type = get_class($html);
	}
	switch($type){
		case "DOMDocument":
		case "DOMElement":
			$id = $html->nodeName."_".md5(uniqid())."_element";
			if($html->nodeName != "#document"){
				$code = "var ".$id." = document.createElement('".$html->nodeName."');\n";
			}
			else{
				$code = "";
			}
			if(!!$html->attributes){ 
				foreach($html->attributes as $attr){
					$code .= $id.".setAttribute('".$attr->name."', '".jse($attr->value)."');\n";
				}
			}
			if(!!$html->childNodes){
				foreach($html->childNodes as $child){
					if($child->nodeType == XML_TEXT_NODE){
						$code .= $id.".appendChild(document.createTextNode('".htmlentities(strip_nl($child->nodeValue))."'));\n";
					}
					else{
						$element = to_dom($child);
						$code .= $element["code"];
						if($html->nodeName != "#document"){
							$code .= $id.".appendChild(".$element["id"].");\n";
						}
						else{
							$id = $element["id"];
						}
					}
				}
			}
			return array("code"=>$code, "id"=>$id);
			break;
		case "DOMDocumentType":
			return array("code"=>"","id"=>"");
			break;
		default:
		case "string":
			$dom = new DOMDocument();
			$dom->strictErrorChecking = false;
			$dom->loadHTML($html);
			$result = to_dom($dom);
			return $result;
			break;
	} 
	return NULL;
}