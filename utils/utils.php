<?php

function read_json() {
	$input = '';

	while ( false !== ( $line = fgets( STDIN ) ) ) {
		$input .= $line;
	}

	$json = json_decode( $input, true );
	if ( !$json ) {
		echo "Invalid JSON.";
		exit(1);
	}

	return $json;
}

