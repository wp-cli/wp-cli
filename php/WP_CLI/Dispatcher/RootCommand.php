<?php

namespace WP_CLI\Dispatcher;

use \WP_CLI\Utils;

/**
 * The root node in the command tree.
 */
class RootCommand extends CompositeCommand {

	function __construct() {
		$this->parent = false;

		$this->name = 'wp';

		$this->shortdesc = 'Manage WordPress through the command-line.';
	}

	function get_longdesc() {
		$binding = array();

		foreach ( \WP_CLI::get_configurator()->get_spec() as $key => $details ) {
			if ( false === $details['runtime'] )
				continue;

			if ( isset( $details['deprecated'] ) )
				continue;

			if ( isset( $details['hidden'] ) )
				continue;

			if ( true === $details['runtime'] )
				$synopsis = "--[no-]$key";
			else
				$synopsis = "--$key" . $details['runtime'];

			$binding['parameters'][] = array(
				'synopsis' => $synopsis,
				'desc' => $details['desc']
			);
		}

		return Utils\mustache_render( 'man-params.mustache', $binding );
	}

	function find_subcommand( &$args ) {
		$command = array_shift( $args );

		Utils\load_command( $command );

		if ( !isset( $this->subcommands[ $command ] ) ) {
			return false;
		}

		return $this->subcommands[ $command ];
	}

	function get_subcommands() {
		Utils\load_all_commands();

		return parent::get_subcommands();
	}
}

