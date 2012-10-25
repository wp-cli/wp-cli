<?php

namespace WP_CLI\Man;

use \WP_CLI\Dispatcher;

function get_path( $args ) {
	return WP_CLI_ROOT . "../../../man/" . implode( '-', $args ) . '.1';
}

function get_doc_path( $args ) {
	return WP_CLI_ROOT . "../../docs/" . implode( '-', $args ) . '.txt';
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
	$fd = fopen( "php://temp", "rw" );

	add_initial_markdown( $fd, $command );

	$doc_path = get_doc_path( $command->get_path() );

	if ( file_exists( $doc_path ) )
		fwrite( $fd, file_get_contents( $doc_path ) );

	fseek( $fd, 0 );

	return $fd;
}

function add_initial_markdown( $fd, Dispatcher\Documentable $command ) {
	$path = $command->get_path();
	$shortdesc = $command->get_shortdesc();
	$synopsis = $command->get_synopsis();

	$synopsis = str_replace( array( '<', '>' ), '_', $synopsis );

	$name_m = implode( '-', $path );
	$name_s = implode( ' ', $path );

	if ( !$shortdesc ) {
		\WP_CLI::warning( "No shortdesc for $name_s" );
	}

	fwrite( $fd, <<<DOC
wp-$name_m(1) -- $shortdesc
====

## SYNOPSIS

`wp $name_s` $synopsis

DOC
	);
}

