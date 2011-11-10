<?php

// Add the command to the wp-cli
WP_CLI::addCommand('eval-file', 'EvalFileCommand');

/**
 * Implement eval-file command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class EvalFileCommand extends WP_CLI_Command {

	/**
	 * Overwrite the constructor to have a command without sub-commands.
     *
     * @param array $args
	 * @param array $assoc_args
	 */
	public function __construct( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp eval-file <path>" );
			exit;
		}

		foreach ( $args as $file ) {
			if ( !file_exists( $file ) ) {
				WP_CLI::error( "'$file' does not exist." );
			} else {
				include( $file );
			}
		}
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
example: wp eval-file some-file.php

Loads and executes a PHP file after bootstrapping WordPress.
EOB
		);
	}
}
