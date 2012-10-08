<?php

/**
 * Base class for WP-CLI commands
 *
 * @package wp-cli
 */
abstract class WP_CLI_Command {

	protected $default_subcommand;

	protected $aliases = array();

	/**
	 * Transfers the handling to the appropriate method
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __construct( $args, $assoc_args ) {
		if ( empty( $args ) )
			$subcommand = $this->default_subcommand;
		else
			$subcommand = array_shift( $args );

		if ( isset( $this->aliases[ $subcommand ] ) )
			$subcommand = $this->aliases[ $subcommand ];

		if ( !method_exists( $this, $subcommand ) ) {
			// This if for reserved keywords in php (like list, isset)
			$subcommand = '_' . $subcommand;
		}

		if ( __FUNCTION__ == $subcommand || !method_exists( $this, $subcommand ) ) {
			WP_CLI::describe_command( get_class( $this ), WP_CLI_COMMAND );
		} else {
			$this->$subcommand( $args, $assoc_args );
		}
	}
}

