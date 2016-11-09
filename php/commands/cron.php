<?php

/**
 * Manage WP-Cron events and schedules.
 *
 * ## EXAMPLES
 *
 *     # Test WP Cron spawning system
 *     $ wp cron test
 *     Success: WP-Cron spawning is working as expected.
 */
class Cron_Command extends WP_CLI_Command {

	/**
	 * Test the WP Cron spawning system and report back its status.
	 *
	 * This command tests the spawning system by performing the following steps:
	 *
	 * * Checks to see if the `DISABLE_WP_CRON` constant is set; errors if true
	 * because WP-Cron is disabled.
	 * * Checks to see if the `ALTERNATE_WP_CRON` constant is set; warns if true.
	 * * Attempts to spawn WP-Cron over HTTP; warns if non 200 response code is
	 * returned.
	 *
	 * ## EXAMPLES
	 *
	 *     # Cron test runs successfully.
	 *     $ wp cron test
	 *     Success: WP-Cron spawning is working as expected.
	 */
	public function test() {

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			WP_CLI::error( 'The DISABLE_WP_CRON constant is set to true. WP-Cron spawning is disabled.' );
		}

		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			WP_CLI::warning( 'The ALTERNATE_WP_CRON constant is set to true. WP-Cron spawning is not asynchronous.' );
		}

		$spawn = self::get_cron_spawn();

		if ( is_wp_error( $spawn ) ) {
			WP_CLI::error( sprintf( 'WP-Cron spawn failed with error: %s', $spawn->get_error_message() ) );
		}

		$code    = wp_remote_retrieve_response_code( $spawn );
		$message = wp_remote_retrieve_response_message( $spawn );

		if ( 200 === $code ) {
			WP_CLI::success( 'WP-Cron spawning is working as expected.' );
		} else {
			WP_CLI::warning( sprintf( 'WP-Cron spawn succeeded but returned HTTP status code: %1$s %2$s', $code, $message ) );
		}

	}

	/**
	 * Spawn a request to `wp-cron.php` and return the response.
	 *
	 * This function is designed to mimic the functionality in `spawn_cron()`
	 * with the addition of returning the result of the `wp_remote_post()`
	 * request.
	 *
	 * @return WP_Error|array The response or WP_Error on failure.
	 */
	protected static function get_cron_spawn() {

		$sslverify     = \WP_CLI\Utils\wp_version_compare( 4.0, '<' );
		$doing_wp_cron = sprintf( '%.22F', microtime( true ) );

		$cron_request = apply_filters( 'cron_request', array(
			'url'  => site_url( 'wp-cron.php?doing_wp_cron=' . $doing_wp_cron ),
			'key'  => $doing_wp_cron,
			'args' => array(
				'timeout'   => 3,
				'blocking'  => true,
				'sslverify' => apply_filters( 'https_local_ssl_verify', $sslverify )
			)
		) );

		# Enforce a blocking request in case something that's hooked onto the 'cron_request' filter sets it to false
		$cron_request['args']['blocking'] = true;

		$result = wp_remote_post( $cron_request['url'], $cron_request['args'] );

		return $result;

	}

}

WP_CLI::add_command( 'cron', 'Cron_Command' );
