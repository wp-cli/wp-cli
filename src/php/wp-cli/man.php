<?php

namespace WP_CLI\Man;

use \WP_CLI\Dispatcher;

function get_path( $args ) {
	return WP_CLI_ROOT . "../../../man/" . implode( '-', $args ) . '.1';
}

function get_doc_path() {
	return WP_CLI_ROOT . "../../docs/";
}

function generate( $command ) {
	if ( $command instanceof Dispatcher\Composite ) {
		foreach ( $command->get_subcommands() as $subcommand ) {
			generate( $subcommand );
		}
		return;
	}

	$descriptorspec = array(
		0 => get_markdown( $command ),
		1 => array( 'file', get_path( $command->get_path() ), 'w' ),
		2 => STDERR
	);

	$r = proc_close( proc_open( "ronn --roff --manual='WP-CLI'", $descriptorspec, $pipes ) );

	\WP_CLI::line( "generated man page for " . implode( '-', $command->get_path() ) );
}

// returns a file descriptor containing markdown that will be passed to ronn
function get_markdown( Dispatcher\Documentable $command ) {
	$path = $command->get_path();
	$shortdesc = $command->get_shortdesc();
	$synopsis = $command->get_synopsis();

	$synopsis = str_replace( array( '<', '>' ), '_', $synopsis );

	$name_m = implode( '-', $path );
	$name_s = implode( ' ', $path );

	if ( !$shortdesc ) {
		\WP_CLI::warning( "No shortdesc for $name_s" );
	}

	$temp = fopen( "php://temp", "rw" );

	fwrite( $temp, <<<DOC
wp-$name_m(1) -- $shortdesc
====

## SYNOPSIS

`wp $name_s` $synopsis

DOC
	);

	$doc_path = get_doc_path() . "$name_m.txt";

	if ( file_exists( $doc_path ) )
		fwrite( $temp, file_get_contents( $doc_path ) );

	fseek( $temp, 0 );

	return $temp;
}

