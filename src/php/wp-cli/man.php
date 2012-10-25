<?php

namespace WP_CLI\Man;

use \WP_CLI\Dispatcher;

function get_path( $args ) {
	return WP_CLI_ROOT . "../../../man/" . implode( '-', $args ) . '.1';
}

function get_src_path( $args ) {
	return WP_CLI_ROOT . "../../docs/" . implode( '-', $args ) . '.txt';
}

function generate( $command ) {
	call_ronn( get_markdown( $command ), get_path( $command->get_path() ) );

	if ( $command instanceof Dispatcher\Composite ) {
		foreach ( $command->get_subcommands() as $subcommand ) {
			generate( $subcommand );
		}
	}
}

// returns a file descriptor or false
function get_markdown( $command ) {
	$fd = fopen( "php://temp", "rw" );

	if ( $command instanceof Dispatcher\Documentable )
		add_initial_markdown( $fd, $command );

	$doc_path = get_src_path( $command->get_path() );

	if ( file_exists( $doc_path ) )
		fwrite( $fd, file_get_contents( $doc_path ) );

	if ( 0 === ftell( $fd ) )
		return false;

	fseek( $fd, 0 );

	return $fd;
}

function add_initial_markdown( $fd, $command ) {
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

function call_ronn( $markdown, $dest ) {
	if ( !$markdown )
		return;

	$descriptorspec = array(
		0 => $markdown,
		1 => array( 'file', $dest, 'w' ),
		2 => STDERR
	);

	$r = proc_close( proc_open( "ronn --roff --manual='WP-CLI'", $descriptorspec, $pipes ) );

	\WP_CLI::line( "generated " . basename( $dest ) );
}

