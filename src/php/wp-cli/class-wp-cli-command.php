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

	public static function get_aliases() {
		return array();
	}

	public function __construct() {}
}

