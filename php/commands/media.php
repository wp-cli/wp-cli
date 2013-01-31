<?php

/**
 * Functionality to control the media library and its attachments
 *
 * @package wp-cli
 */
class Media_Command extends WP_CLI_Command {

	function __construct() {
		WP_Filesystem();
	}

	/**
	 * Import a file into the media library.
	 *
	 * @synopsis <filename> [--blog]
	 */
	function import( $args, $assoc_args = array() ) {
	}
	/**
	 * Regenerate thumbnail(s)
	 *
	 * @synopsis [--all] [--file=<file>] [--id=<id>]
	 */
	function regenerate( $args, $assoc_args = array() ) {

	}
	
}

WP_CLI::add_command( 'media', 'Media_Command' );