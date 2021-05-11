<?php

namespace WP_CLI\Compat\Min_PHP_5_6;

trait FeedbackMethodTrait {

	/**
	 * @param string $string
	 * @param mixed  ...$args Optional text replacements.
	 */
	public function feedback( $string, ...$args ) { // phpcs:ignore PHPCompatibility.LanguageConstructs.NewLanguageConstructs.t_ellipsisFound
		$args_array = [];
		foreach ( $args as $arg ) {
			$args_array[] = $args;
		}

		$this->process_feedback( $string, $args );
	}
}
