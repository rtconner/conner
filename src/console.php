<?php

namespace console;

/**
 * Return arg list
 */
function args() {
	global $argv;
	$args = $argv;
	array_shift($args);
	return $args;
}

/**
 * Return single arg with index starting at zero
 */
function arg($index) {
	$args = args();
	if(array_key_exists($index, $args)) {
		return $args[$index];
	} else {
		error('Missing Argument');
		exit;	
	}
}

/**
 * Output a string with line break at end
 */
function out($str) {
	echo $str."\n";
}

/**
 * Output line break
 */
function endl() {
	echo "\n";
}

/**
 * Output text as stderror to console
 */
function error($str) {
	echo '<error>'.$str.'</error>'."\n";
}