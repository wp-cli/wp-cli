<?php

namespace WP_CLI\Man;

use \WP_CLI\Dispatcher;

function get_file_name( $args ) {
	return implode( '-', $args ) . '.1';
}

function get_src_file_name( $args ) {
	return implode( '-', $args ) . '.txt';
}

function generate( $src_dir, $dest_dir, $command ) {
	$src_path = $src_dir . get_src_file_name( $command->get_path() );
	$dest_path = $dest_dir . get_file_name( $command->get_path() );

	call_ronn( get_markdown( $src_path, $command ), $dest_path );

	if ( $command instanceof Dispatcher\Composite ) {
		foreach ( $command->get_subcommands() as $subcommand ) {
			generate( $src_dir, $dest_dir, $subcommand );
		}
	}
}

// returns a file descriptor or false
function get_markdown( $doc_path, $command ) {
	if ( !file_exists( $doc_path ) )
		return false;

	$fd = fopen( "php://temp", "rw" );

	if ( $command instanceof Dispatcher\Documentable )
		add_initial_markdown( $fd, $command );

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

	$cmd = "ronn --date=2012-01-01 --roff --manual='WP-CLI'";

	$r = proc_close( proc_open( $cmd, $descriptorspec, $pipes ) );

	$roff = file_get_contents( $dest );
	$roff = str_replace( ' "January 2012"', '', $roff );
	file_put_contents( $dest, $roff );

	\WP_CLI::line( "generated " . basename( $dest ) );
}

