<?php

namespace WP_CLI\Dispatcher;

use WP_CLI;

/**
 * Adds a command namespace without actual functionality.
 *
 * This is meant to provide the means to attach meta information to a namespace
 * when there's no actual command needed.
 *
 * In case a real command gets registered for the same name, it replaces the
 * command namespace.
 *
 * @package WP_CLI
 */
class CommandNamespace extends CompositeCommand {

	/**
	 * Show the usage for all subcommands contained
	 * by the composite command.
	 */
	public function show_usage() {
		$methods = $this->get_subcommands();

		$i     = 0;
		$count = 0;

		foreach ( $methods as $subcommand ) {
			$prefix = ( 0 === $i ) ? 'usage: ' : '   or: ';
			$i++;

			if ( \WP_CLI::get_runner()->is_command_disabled( $subcommand ) ) {
				continue;
			}

			\WP_CLI::line( $subcommand->get_usage( $prefix ) );
			$count++;
		}

		$cmd_name = implode( ' ', array_slice( get_path( $this ), 1 ) );
		$message  = $count > 0
			? "See 'wp help $cmd_name <command>' for more information on a specific command."
			: "The namespace $cmd_name does not contain any usable commands in the current context.";

		\WP_CLI::line();
		\WP_CLI::line( $message );

	}
}
