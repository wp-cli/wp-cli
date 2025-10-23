<?php

use cli\Shell;
use WP_CLI\Dispatcher;
use WP_CLI\Utils;
use WP_CLI\Process;

class Help_Command extends WP_CLI_Command {

	/**
	 * Gets help on WP-CLI, or on a specific command.
	 *
	 * ## OPTIONS
	 *
	 * [<command>...]
	 * : Get help on a specific command.
	 *
	 * ## EXAMPLES
	 *
	 *     # get help for `core` command
	 *     wp help core
	 *
	 *     # get help for `core download` subcommand
	 *     wp help core download
	 *
	 * @param string[] $args
	 */
	public function __invoke( $args ) {
		$r = WP_CLI::get_runner()->find_command_to_run( $args );

		if ( is_array( $r ) ) {
			list( $command ) = $r;

			self::show_help( $command );
			exit;
		}
	}

	private static function show_help( $command ) {
		$out = self::get_initial_markdown( $command );

		// Remove subcommands if in columns - will wordwrap separately.
		$subcommands       = '';
		$column_subpattern = '[ \t]+[^\t]+\t+';
		if ( preg_match( '/(^## SUBCOMMANDS[^\n]*\n+' . $column_subpattern . '.+?)(?:^##|\z)/ms', $out, $matches, PREG_OFFSET_CAPTURE ) ) {
			$subcommands        = $matches[1][0];
			$subcommands_header = "## SUBCOMMANDS\n";
			$out                = substr_replace( $out, $subcommands_header, $matches[1][1], strlen( $subcommands ) );
		}

		$out .= self::parse_reference_links( $command->get_longdesc() );

		// Definition lists.
		$out = (string) preg_replace_callback( '/([^\n]+)\n: (.+?)(\n\n|$)/s', [ __CLASS__, 'rewrap_param_desc' ], $out );

		// Ensure lines with no leading whitespace that aren't section headers are indented.
		$out = (string) preg_replace( '/^((?! |\t|##).)/m', "\t$1", $out );

		$tab = str_repeat( ' ', 2 );

		// Need to de-tab for wordwrapping to work properly.
		$out = str_replace( "\t", $tab, $out );

		$wordwrap_width = Shell::columns();

		// Wordwrap with indent.
		$out = (string) preg_replace_callback(
			'/^( *)([^\n]+)\n/m',
			static function ( $matches ) use ( $wordwrap_width ) {
				return $matches[1] . str_replace( "\n", "\n{$matches[1]}", wordwrap( $matches[2], $wordwrap_width - strlen( $matches[1] ) ) ) . "\n";
			},
			$out
		);

		if ( $subcommands ) {
			// Wordwrap with column indent.
			$subcommands = (string) preg_replace_callback(
				'/^(' . $column_subpattern . ')([^\n]+)\n/m',
				static function ( $matches ) use ( $wordwrap_width, $tab ) {
					// Need to de-tab for wordwrapping to work properly.
					$matches[1]  = str_replace( "\t", $tab, $matches[1] );
					$matches[2]  = str_replace( "\t", $tab, $matches[2] );
					$padding_len = strlen( $matches[1] );
					$padding     = str_repeat( ' ', $padding_len );
					return $matches[1] . str_replace( "\n", "\n$padding", wordwrap( $matches[2], $wordwrap_width - $padding_len ) ) . "\n";
				},
				$subcommands
			);

			// Put subcommands back.
			$out = str_replace( $subcommands_header, $subcommands, $out );
		}

		// Section headers.
		$out = (string) preg_replace( '/^## ([A-Z ]+)/m', WP_CLI::colorize( '%9\1%n' ), $out );

		self::pass_through_pager( $out );
	}

	private static function rewrap_param_desc( $matches ) {
		$param = $matches[1];
		$desc  = self::indent( "\t\t", $matches[2] );
		return "\t$param\n$desc\n\n";
	}

	private static function indent( $whitespace, $text ) {
		$lines = explode( "\n", $text );
		foreach ( $lines as &$line ) {
			$line = $whitespace . $line;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Locate an executable binary by name using `which`.
	 *
	 * If the binary requested is an alias instead of a binary, this function may return an emptystring.
	 *
	 * This may not work accurately in PowerShell.
	 *
	 * @param string $binary Name of the binary to be found
	 * @return boolean True if this command has determined that the binary or other command exists.
	 */
	public static function binary_exists( $binary ) {
		if ( Utils\is_windows() ) {
			// This may not work in PowerShell; see https://stackoverflow.com/a/304447
			// If this needs to be adjusted to use 'where.exe' for PowerShell,
			// then we will need to add a way of detecting whether wp-cli is running in PowerShell.
			$detector = 'where';
		} else {
			// POSIX method to detect whether a command exists
			// This sometimes detects aliases.
			$detector = 'command -v';
		}

		$result = Process::create( "$detector $binary", null, null )->run();

		if ( 0 !== $result->return_code ) {
			// We could not reliably determine that the binary exists
			return false;
		} else {
			// POSIX binaries: command -v will return the path and exit 0
			// aliases: command -v may return the alias command and exit 0
			return true;
		}
	}

	/**
	 * Determine whether to use `less` or `more` as a pager
	 *
	 * This caches the determined pager.
	 *
	 * @return string The command to use for the pager. Defaults to `more`.
	 */
	public static function locate_pager() {
		static $pager = null;

		if ( empty( $pager ) ) {
			if ( self::binary_exists( 'less' ) ) {
				// less is not available in all systems
				$pager = 'less -R';
			} else {
				// more is part of the POSIX definition, and is also available on Windows.
				$pager = 'more';
			}
		}

		return $pager;
	}

	/**
	 * Pass a given set of output through the system's terminal pager.
	 *
	 * @param string $out The output to be run through the pager.
	 * @return mixed Termination status of the pager as reported by https://www.php.net/manual/en/function.proc-close.php
	 */
	private static function pass_through_pager( $out ) {

		if ( ! Utils\check_proc_available( null /*context*/, true /*return*/ ) ) {
			WP_CLI::line( $out );
			WP_CLI::debug( 'Warning: check_proc_available() failed in pass_through_pager().' );
			return -1;
		}

		$pager = getenv( 'PAGER' );
		// if '' we should assume that the user has explicitly disabled the pager by setting `PAGER=`
		if ( false === $pager ) {
			$pager = self::locate_pager();
		}

		// For Windows 7 need to set code page to something other than Unicode (65001) to get around "Not enough memory." error with `more.com` on PHP 7.1+.
		if ( 'more' === $pager && defined( 'PHP_WINDOWS_VERSION_MAJOR' ) && PHP_WINDOWS_VERSION_MAJOR < 10 ) {
			// Note will also apply to Windows 8 (see https://msdn.microsoft.com/en-us/library/windows/desktop/ms724832.aspx) but probably harmless anyway.
			$cp = (int) getenv( 'WP_CLI_WINDOWS_CODE_PAGE' ) ?: 1252; // Code page 1252 is the most used so probably the most compat.
			sapi_windows_cp_set( $cp );
		}

		// Convert string to file handle.
		$fd = fopen( 'php://temp', 'r+b' );
		if ( $fd ) {
			fwrite( $fd, $out );
			rewind( $fd );
		}

		/**
		 * @var array<resource> $descriptorspec
		 */
		$descriptorspec = [
			0 => $fd,
			1 => STDOUT,
			2 => STDERR,
		];

		$process = Utils\proc_open_compat( $pager, $descriptorspec, $pipes );
		return $process ? proc_close( $process ) : -1;
	}

	private static function get_initial_markdown( $command ) {
		$name = implode( ' ', Dispatcher\get_path( $command ) );

		$binding = [
			'name'      => $name,
			'shortdesc' => $command->get_shortdesc(),
		];

		$binding['synopsis'] = "$name " . $command->get_synopsis();

		$alias = $command->get_alias();
		if ( $alias ) {
			$binding['alias'] = $alias;
		}
		$hook_name        = $command->get_hook();
		$hook_description = $hook_name ? Utils\get_hook_description( $hook_name ) : null;
		if ( $hook_description && 'after_wp_load' !== $hook_name ) {
			if ( $command->can_have_subcommands() ) {
				$binding['shortdesc'] .= "\n\nUnless overridden, these commands run on the '$hook_name' hook, $hook_description";
			} else {
				$binding['shortdesc'] .= "\n\nThis command runs on the '$hook_name' hook, $hook_description";
			}
		}

		if ( $command->can_have_subcommands() ) {
			$binding['has-subcommands']['subcommands'] = self::render_subcommands( $command );
		}

		return Utils\mustache_render( 'man.mustache', $binding );
	}

	private static function render_subcommands( $command ) {
		$subcommands = [];
		foreach ( $command->get_subcommands() as $subcommand ) {

			if ( WP_CLI::get_runner()->is_command_disabled( $subcommand ) ) {
				continue;
			}

			$subcommands[ $subcommand->get_name() ] = $subcommand->get_shortdesc();
		}

		$max_len = self::get_max_len( array_keys( $subcommands ) );

		$lines = [];
		foreach ( $subcommands as $name => $desc ) {
			$lines[] = str_pad( $name, $max_len ) . "\t\t\t" . $desc;
		}

		return $lines;
	}

	private static function get_max_len( $strings ) {
		$max_len = 0;
		foreach ( $strings as $str ) {
			$len = strlen( $str );
			if ( $len > $max_len ) {
				$max_len = $len;
			}
		}

		return $max_len;
	}

	/**
	 * Parse reference links from longdescription.
	 *
	 * @param  string $longdesc The longdescription from the `$command->get_longdesc()`.
	 * @return string The longdescription which has links as footnote.
	 */
	private static function parse_reference_links( $longdesc ) {
		$description = '';
		foreach ( explode( "\n", $longdesc ) as $line ) {
			if ( 0 === strpos( $line, '#' ) ) {
				break;
			}
			$description .= $line . "\n";
		}

		// Fires if it has description text at the head of `$longdesc`.
		if ( $description ) {
			$links   = []; // An array of URLs from the description.
			$pattern = '/\[.+?\]\((https?:\/\/.+?)\)/';
			$newdesc = (string) preg_replace_callback(
				$pattern,
				static function ( $matches ) use ( &$links ) {
					static $count = 0;
					$count++;
					$links[] = $matches[1];
					return str_replace( '(' . $matches[1] . ')', '[' . $count . ']', $matches[0] );
				},
				$description
			);

			$footnote = '';
			foreach ( $links as $i => $link ) {
				$n         = $i + 1;
				$footnote .= '[' . $n . '] ' . $link . "\n";
			}

			if ( $footnote ) {
				$newdesc  = trim( $newdesc ) . "\n\n---\n" . $footnote;
				$longdesc = str_replace( trim( $description ), trim( $newdesc ), $longdesc );
			}
		}

		return $longdesc;
	}
}
