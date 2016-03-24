<?php

namespace WP_CLI;

use \Composer\IO\NullIO;
use \WP_CLI;

/**
 * A Composer IO class so we can provide some level of interactivity from WP-CLI
 */
class ComposerIO extends NullIO {

	/**
	 * {@inheritDoc}
	 */
	public function isVerbose() {
		return true;
	}

	/**
     * {@inheritDoc}
     */
	public function write( $messages, $newline = true, $verbosity = self::NORMAL ) {
		self::output_clean_message( $messages );
	}

	/**
	 * {@inheritDoc}
	 */
	public function writeError( $messages, $newline = true, $verbosity = self::NORMAL ) {
		self::output_clean_message( $messages );
	}

	private static function output_clean_message( $message ) {
		$message = preg_replace( '#<(https?)([^>]+)>#', '$1$2', $message );
		WP_CLI::log( strip_tags( trim( $message ) ) );
	}

}
