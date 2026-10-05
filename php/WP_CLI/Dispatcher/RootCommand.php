<?php

namespace WP_CLI\Dispatcher;

use WP_CLI\Utils;

/**
 * The root node in the command tree.
 *
 * @package WP_CLI
 */
class RootCommand extends CompositeCommand {

	/**
	 * Instantiate a new RootCommand.
	 */
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
	 * Get a directly registered subcommand without materializing it.
	 *
	 * @param string $name Subcommand name.
	 * @return Subcommand|CompositeCommand|false
	 */
	public function get_registered_subcommand( $name ) {
		Utils\load_command( $name );

		return parent::get_registered_subcommand( $name );
	}

	/**
	 * Find a subcommand registered on the root
	 * command.
	 *
	 * @param array<string> $args
	 * @return Subcommand|CompositeCommand|false
	 */
	public function find_subcommand( &$args ) {
		$command = array_shift( $args );
		if ( null === $command ) {
			return false;
		}

		Utils\load_command( $command );

		if ( ! isset( $this->subcommands[ $command ] ) ) {
			$this->run_command_loaders( $command );
		}

		if ( ! isset( $this->subcommands[ $command ] ) ) {
			return false;
		}

		return $this->materialize_subcommand( $command );
	}
}
