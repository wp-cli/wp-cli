<?php

/**
 * Base class for WP-CLI commands
 *
 * @package wp-cli
 */
abstract class WP_CLI_Command {

	public static function get_default_subcommand() {
		return false;
	}

	public function __construct() {}
}

