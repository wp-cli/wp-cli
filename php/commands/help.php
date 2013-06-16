<?php

use \WP_CLI\Utils;
use \WP_CLI\Dispatcher;

class Help_Command extends WP_CLI_Command {

	/**
	 * Get help on a certain topic.
	 *
	 * @synopsis [<command>] [--gen]
	 */
	function __invoke( $args, $assoc_args ) {
		if ( isset( $assoc_args['gen'] ) )
			$this->generate( $args );
		else
			$this->show( $args );

		// WordPress is already loaded, so there's no chance we'll find the command
		if ( function_exists( 'add_filter' ) ) {
			\WP_CLI::error( sprintf( "'%s' is not a registered wp command.", $args[0] ) );
		}
	}

	private static function find_subcommand( $args ) {
		$command = \WP_CLI::$root;

		while ( !empty( $args ) && $command && $command->has_subcommands() ) {
			$command = $command->find_subcommand( $args );
		}

		return $command;
	}

	private function show( $args ) {
		if ( self::maybe_show_manpage( $args ) ) {
			exit;
		}

		$command = self::find_subcommand( $args );

		if ( $command ) {
			$command->show_usage();
			exit;
		}
	}

	private function generate( $args ) {
		if ( '' === exec( 'which ronn' ) ) {
			WP_CLI::error( '`ronn` executable not found.' );
		}

		$command = self::find_subcommand( $args );

		if ( $command ) {
			foreach ( WP_CLI::get_man_dirs() as $dest_dir => $src_dir ) {
				self::_generate( $src_dir, $dest_dir, $command );
			}
			exit;
		}
	}

	private static function maybe_show_manpage( $args ) {
		$man_file = self::get_file_name( $args );

		foreach ( \WP_CLI::get_man_dirs() as $dest_dir => $_ ) {
			$man_path = "$dest_dir/" . $man_file;

			if ( is_readable( $man_path ) ) {
				self::show_manpage( $man_path );
				return true;
			}
		}

		return false;
	}

	private static function show_manpage( $path ) {
		// man can't read phar://, so need to copy to a temporary file
		$tmp_path = tempnam( sys_get_temp_dir(), 'wp-cli-man-' );

		copy( $path, $tmp_path );

		\WP_CLI::launch( "man $tmp_path" );

		unlink( $tmp_path );
	}

	private static function _generate( $src_dir, $dest_dir, $command ) {
		$cmd_path = Dispatcher\get_path( $command );
		array_shift( $cmd_path ); // discard 'wp'

		$src_path = "$src_dir/" . self::get_src_file_name( $cmd_path );
		$dest_path = "$dest_dir/" . self::get_file_name( $cmd_path );

		self::call_ronn( self::get_markdown( $src_path, $command ), $dest_path );

		if ( $command->has_subcommands() ) {
			foreach ( $command->get_subcommands() as $subcommand ) {
				self::_generate( $src_dir, $dest_dir, $subcommand );
			}
		}
	}

	// returns a file descriptor or false
	private static function get_markdown( $doc_path, $command ) {
		if ( !file_exists( $doc_path ) )
			return false;

		$fd = fopen( "php://temp", "rw" );

		self::add_initial_markdown( $fd, $command );

		fwrite( $fd, file_get_contents( $doc_path ) );

		if ( 0 === ftell( $fd ) )
			return false;

		fseek( $fd, 0 );

		return $fd;
	}

	private static function add_initial_markdown( $fd, $command ) {
		$path = Dispatcher\get_path( $command );

		$binding = array(
			'name_m' => implode( '-', $path ),
			'shortdesc' => $command->get_shortdesc(),
		);

		$synopsis = Dispatcher\get_full_synopsis( $command, true );

		$synopsis = str_replace( '_', '\_', $synopsis );
		$synopsis = str_replace( array( '<', '>' ), '_', $synopsis );

		$binding['synopsis'] = $synopsis;

		if ( !$binding['shortdesc'] ) {
			$name_s = implode( ' ', $path );
			\WP_CLI::warning( "No shortdesc for $name_s" );
		}

		if ( $command->has_subcommands() ) {
			foreach ( $command->get_subcommands() as $subcommand ) {
				$binding['has-subcommands']['subcommands'][] = array(
					'name' => $subcommand->get_name(),
					'desc' => $subcommand->get_shortdesc(),
				);
			}
		}

		fwrite( $fd, Utils\mustache_render( 'man.mustache', $binding ) );
	}

	private static function call_ronn( $markdown, $dest ) {
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

		\WP_CLI::log( "generated " . basename( $dest ) );
	}

	private static function get_file_name( $args ) {
		return implode( '-', $args ) . '.1';
	}

	private static function get_src_file_name( $args ) {
		return implode( '-', $args ) . '.txt';
	}
}

WP_CLI::add_command( 'help', 'Help_Command' );

