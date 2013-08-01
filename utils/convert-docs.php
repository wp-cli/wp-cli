<?php

# usage: php utils/convert-docs.php man-src/*.txt

function convert_file( $path ) {
	$out = file_get_contents( $path );

	// options to definition lists
	$out = preg_replace_callback( '/\n\* (.+?):?\n\n\t/', function( $matches ) {
		$arg = str_replace( '`', '', $matches[1] );

		return "\n$arg\n: ";
	}, $out );

	// convert tabs to spaces
	$out = preg_replace( '/^\t/m', "  ", $out );

	// prepend docblock notation
	# $out = preg_replace( '/^(.*)/m', "\t* \\1", $out );

	file_put_contents( $path, $out );
}

foreach ( array_slice( $argv, 1 ) as $arg ) {
	convert_file( $arg );
}
