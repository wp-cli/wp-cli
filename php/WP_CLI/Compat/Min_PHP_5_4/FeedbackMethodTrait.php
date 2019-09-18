<?php

namespace WP_CLI\Compat\Min_PHP_5_4;

trait FeedbackMethodTrait {

	/**
	 * @param string $string
	 */
	public function feedback( $string ) {
		$args = func_get_args();
		$args = array_splice( $args, 1 );

		$this->process_feedback( $string, $args );
	}
}
