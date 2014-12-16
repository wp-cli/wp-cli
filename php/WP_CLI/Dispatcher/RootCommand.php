<?php

namespace WP_CLI\Dispatcher;

use \WP_CLI\Utils;

/**
 * The root node in the command tree.
 *
 * @package WP_CLI
 */
class RootCommand extends CompositeCommand {

	public function __construct() {
		$this->parent = false;

		$this->name = 'wp';

		$this->shortdesc = 'Manage WordPress through the command-line.';
	}

	/**
	 * Get the human-readable long description.
	 *
	 * @return string
	 */
	public function get_longdesc() {
		return $this->get_global_params( true );
	}

	/**
	 * Find a subcommand registered on the root
	 * command.
	 *
	 * @param array $args
	 * @return \WP_CLI\Dispatcher\Subcommand|false
	 */
	public function find_subcommand( &$args ) {
		$command = array_shift( $args );

		Utils\load_command( $command );

		if ( !isset( $this->subcommands[ $command ] ) ) {
			return false;
		}

		return $this->subcommands[ $command ];
	}

	/**
	 * Get all registered subcommands.
	 *
	 * @return array
	 */
	public function get_subcommands() {
		Utils\load_all_commands();

		return parent::get_subcommands();
	}
}

