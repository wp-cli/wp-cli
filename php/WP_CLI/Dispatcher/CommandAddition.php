<?php

namespace WP_CLI\Dispatcher;

use WP_CLI;

/**
 * Controls whether adding of a command should be completed or not.
 *
 * This is needed because we can't reliably pass scalar values by reference
 * through the hooks mechanism. An object is always passed by reference.
 *
 * @package WP_CLI
 */
final class CommandAddition {

	/**
	 * Whether the command addition was aborted or not.
	 *
	 * @var bool
	 */
	private $abort = false;

	/**
	 * Reason for which the addition was aborted.
	 *
	 * @var string
	 */
	private $reason = '';

	/**
	 * Abort the current command addition.
	 *
	 * @param string $reason Reason as to why the addition was aborted.
	 */
	public function abort( $reason = '' ) {
		$this->abort  = true;
		$this->reason = (string) $reason;
	}

	/**
	 * Check whether the command addition was aborted.
	 *
	 * @return bool
	 */
	public function was_aborted() {
		return $this->abort;
	}

	/**
	 * Get the reason as to why the addition was aborted.
	 *
	 * @return string
	 */
	public function get_reason() {
		return $this->reason;
	}
}
