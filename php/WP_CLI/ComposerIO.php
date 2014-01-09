<?php

namespace WP_CLI;

use \Composer\IO\NullIO;

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
    public function write($messages, $newline = true) {
		\WP_CLI::line( " - " . strip_tags( $messages ) );
	}

}