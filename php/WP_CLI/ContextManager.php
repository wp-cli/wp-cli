<?php

namespace WP_CLI;

use WP_CLI;

/**
 * Context manager to register_context and process different contexts that commands can
 * run within.
 */
final class ContextManager {

	/**
	 * Associative array of context implementations.
	 *
	 * @var array<string, Context>
	 */
	private $contexts = [];

	/**
	 * Store the current context.
	 *
	 * @var string Current context.
	 */
	private $current_context = Context::CLI;

	/**
	 * Register a context with WP-CLI.
	 *
	 * @param string  $name           Name of the context.
	 * @param Context $implementation Implementation of the context.
	 */
	public function register_context( $name, Context $implementation ) {
		$this->contexts[ $name ] = $implementation;
	}

	/**
	 * Switch the context in which to run WP-CLI.
	 *
	 * @param array $config Associative array of configuration data.
	 * @return void
	 *
	 * @throws ExitException When an invalid context was requested.
	 */
	public function switch_context( $config ) {
		$context = isset( $config['context'] )
			? $config['context']
			: $this->current_context;

		if ( ! array_key_exists( $context, $this->contexts ) ) {
			WP_CLI::error( "Unknown context '{$context}'" );
		}

		WP_CLI::debug( "Using context '{$context}'", Context::DEBUG_GROUP );

		$this->current_context = $context;
		$this->contexts[ $context ]->process( $config );
	}

	/**
	 * Return the current context.
	 *
	 * @return string Current context.
	 */
	public function get_context() {
		return $this->current_context;
	}
}
