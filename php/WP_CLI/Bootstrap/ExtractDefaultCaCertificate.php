<?php

namespace WP_CLI\Bootstrap;

use WP_CLI\Utils;
use WpOrg\Requests\Requests;

/**
 * Class ExtractDefaultCaCertificate.
 *
 * Check to see if the default ca certificate is pointing into a Phar archive.
 * If that is the case, extract the certificate into a temporary file and
 * adapt the configuration accordingly.
 *
 * This is done because cURL cannot load the certificate from within the Phar
 * archive and requires a regular file to work with.
 *
 * @package WP_CLI\Bootstrap
 */
final class ExtractDefaultCaCertificate implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
        // We're using the get_default_cacert() helper, which automatically
        // checks and extract files inside Phar archives.
		Requests::set_certificate_path( Utils\get_default_cacert() );

		return $state;
	}
}
