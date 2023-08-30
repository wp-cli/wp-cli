<?php

namespace WP_CLI\Dispatcher;

/**
 * Get the path to a command, e.g. "core download"
 *
 * @param Subcommand|CompositeCommand $command
 * @return string[]
 */
function get_path( $command ) {
	$path = [];

	do {
		array_unshift( $path, $command->get_name() );
		$command = $command->get_parent();
	} while ( $command );

	return $path;
}
