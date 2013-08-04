<?php

# usage: php utils/convert-docs.php man-src/*.txt

function convert_file( $path ) {
	$out = file_get_contents( $path );

	// options to definition lists
	$out = preg_replace_callback( '/\n\* (.+?):?\n\n\t/', function( $matches ) {
		$arg = str_replace( '`', '', $matches[1] );

		return "\n$arg\n: ";
	}, $out );

	// fix indentation
	$out = preg_replace( '/^  ([^ ]+)/m', "\t\\1", $out );
	$out = str_replace( "\t", '    ', $out );

	// prepend docblock notation
	$out = preg_replace( '/^/m', "\t * ", $out );

	file_put_contents( $path, $out );
}

foreach ( array_slice( $argv, 1 ) as $arg ) {
	convert_file( $arg );
}
