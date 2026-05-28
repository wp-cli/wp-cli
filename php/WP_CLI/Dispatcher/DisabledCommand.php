<?php

namespace WP_CLI\Dispatcher;

use WP_CLI;
use WP_CLI\DocParser;

/**
 * A command that has been disabled.
 *
 * @package WP_CLI
 */
class DisabledCommand extends Subcommand {

	/**
	 * Reason why the command is disabled.
	 *
	 * @var string
	 */
	private $disabled_reason;

	/**
	 * Instantiate a new DisabledCommand.
	 *
	 * @param RootCommand|CompositeCommand $parent_command Parent command.
	 * @param string                       $name           Command name.
	 * @param DocParser                    $docparser      DocParser instance.
	 * @param string                       $reason         Reason why the command is disabled.
	 */
	public function __construct( $parent_command, $name, $docparser, $reason ) {
		// Pass a dummy closure for when_invoked since it should not be run.
		parent::__construct( $parent_command, $name, $docparser, function () {} );
		$this->disabled_reason = $reason;
	}

	/**
	 * Get the reason why the command is disabled.
	 *
	 * @return string
	 */
	public function get_disabled_reason() {
		return $this->disabled_reason;
	}

	/**
	 * Prevent execution of the command.
	 *
	 * @param array $args
	 * @param array $assoc_args
	 * @param array $extra_args
	 */
	public function invoke( $args, $assoc_args, $extra_args ) {
		$cmd_path = implode( ' ', get_path( $this ) );
		$reason   = $this->disabled_reason ? " Reason: {$this->disabled_reason}" : '';
		WP_CLI::error( sprintf( "The '%s' command has been disabled.%s", $cmd_path, $reason ) );
	}
}
