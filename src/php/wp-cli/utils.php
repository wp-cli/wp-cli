<?php

namespace WP_CLI\Utils;

/**
 * Splits $argv into positional and associative arguments.
 *
 * @param string
 * @return array
 */
function parse_args() {
	$regular_args = array();
	$assoc_args = array();

	foreach ( array_slice( $GLOBALS['argv'], 1 ) as $arg ) {
		if ( preg_match( '|^--([^=]+)$|', $arg, $matches ) ) {
			$assoc_args[ $matches[1] ] = true;
		} elseif ( preg_match( '|^--([^=]+)=(.+)|', $arg, $matches ) ) {
			$assoc_args[ $matches[1] ] = $matches[2];
		} else {
			$regular_args[] = $arg;
		}
	}

	return array( $regular_args, $assoc_args );
}

