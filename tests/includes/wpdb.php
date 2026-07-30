<?php

interface WP_CLI_Mock_WPDB {
	/**
	 * @param string $input
	 * @return string
	 */
	public function esc_like( $input );
}
