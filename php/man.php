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
	$cmd_path = Dispatcher\get_path( $command );
	array_shift( $cmd_path ); // discard 'wp'

	$src_path = $src_dir . get_src_file_name( $cmd_path );
	$dest_path = $dest_dir . get_file_name( $cmd_path );

	call_ronn( get_markdown( $src_path, $command ), $dest_path );

	if ( $command instanceof Dispatcher\CommandContainer ) {
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
	$path = Dispatcher\get_path( $command );

	$shortdesc = $command->get_shortdesc();
	$synopsis = $command->get_full_synopsis();

	$synopsis = str_replace( '_', '\_', $synopsis );
	$synopsis = str_replace( array( '<', '>' ), '_', $synopsis );

	$name_m = implode( '-', $path );
	$name_s = implode( ' ', $path );

	if ( !$shortdesc ) {
		\WP_CLI::warning( "No shortdesc for $name_s" );
	}

	fwrite( $fd, <<<DOC
$name_m(1) -- $shortdesc
====

## SYNOPSIS

$synopsis

DOC
	);

	if ( $command instanceof Dispatcher\CommandContainer ) {

		fwrite( $fd, <<<DOC
## SUBCOMMANDS

DOC
		);

		foreach ( $command->get_subcommands() as $subcommand ) {
			$name = $subcommand->get_name();
			$desc = $subcommand->get_shortdesc();

			fwrite( $fd, <<<DOC
* `$name`:

	$desc

DOC
	);
		}
	}
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

function show_manpage( $path ) {
	// man can't read phar://, so need to copy to a temporary file
	$tmp_path = tempnam( sys_get_temp_dir(), 'wp-cli-man-' );

	copy( $path, $tmp_path );

	\WP_CLI::launch( "man $tmp_path" );

	unlink( $tmp_path );
}

function maybe_show_manpage( $args ) {
	$man_file = get_file_name( $args );

	foreach ( \WP_CLI::get_man_dirs() as $dest_dir => $_ ) {
		$man_path = $dest_dir . $man_file;

		if ( is_readable( $man_path ) ) {
			show_manpage( $man_path );
			return true;
		}
	}

	return false;
}

