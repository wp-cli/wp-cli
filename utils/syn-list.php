<?php
# Given a list of commands as JSON on STDIN, generates a list of synopses.
#
# Example usage:
#
# wp --cmd-dump | php /path/to/wp-cli/utils/syn-list.php

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

function generate_synopsis( $command, $path = '' ) {
	$full_path = $path . ' ' . $command['name'];

	if ( !isset( $command['subcommands'] ) ) {
		echo $full_path . ' ' . $command['synopsis'] . "\n";
	} else {
		foreach ( $command['subcommands'] as $subcommand ) {
			generate_synopsis( $subcommand, $full_path );
		}
	}
}

generate_synopsis( read_json() );

