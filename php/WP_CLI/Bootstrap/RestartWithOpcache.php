<?php

namespace WP_CLI\Bootstrap;

use WP_CLI;
use WP_CLI\Autoloader;
use WP_CLI\OpcacheRestarter;
use WP_CLI\Utils;

/**
 * Class RestartWithOpcache.
 *
 * Restarts PHP with OPcache's file cache enabled when `WP_CLI_OPCACHE` is set,
 * as an environment variable or in the `env` section of a config file, so
 * compiled code is reused between runs.
 *
 * This runs before any `--require` files or packages are loaded, so that they
 * only run in the restarted process.
 *
 * @package WP_CLI\Bootstrap
 */
final class RestartWithOpcache implements BootstrapStep {

	/**
	 * Process this single bootstrapping step.
	 *
	 * @param BootstrapState $state Contextual state to pass into the step.
	 *
	 * @return BootstrapState Modified state to pass to the next step.
	 */
	public function process( BootstrapState $state ) {
		if ( ! Utils\get_env_or_config( 'WP_CLI_OPCACHE' ) || ! extension_loaded( 'Zend OPcache' ) ) {
			return $state;
		}

		// `opcache.enable` is on by default, so this was turned off on purpose. OPcache
		// wouldn't cache anything in the restarted process either.
		if ( ! ini_get( 'opcache.enable' ) ) {
			WP_CLI::debug( 'Not restarting with OPcache: opcache.enable is off.', 'bootstrap' );
			return $state;
		}

		// The Composer autoloader is only loaded by a later step. Loading it now would also
		// run the files it includes, like command registrations, in a process that is about
		// to be replaced. So only register the namespaces the restart needs.
		if ( ! class_exists( 'Composer\XdebugHandler\XdebugHandler', false ) ) {
			( new Autoloader() )
				->add_namespace( 'Composer\XdebugHandler', WP_CLI_VENDOR_DIR . '/composer/xdebug-handler/src' )
				->add_namespace( 'Composer\Pcre', WP_CLI_VENDOR_DIR . '/composer/pcre/src' )
				->register();
		}

		if ( ! class_exists( 'Composer\XdebugHandler\XdebugHandler' ) ) {
			WP_CLI::debug( 'Not restarting with OPcache: composer/xdebug-handler is not available.', 'bootstrap' );
			return $state;
		}

		$cache_dir = Utils\get_cache_dir() . '/opcache';
		if ( ! is_dir( $cache_dir ) && ! @mkdir( $cache_dir, 0700, true ) && ! is_dir( $cache_dir ) ) {
			WP_CLI::debug( "Not restarting with OPcache: cannot create {$cache_dir}.", 'bootstrap' );
			return $state;
		}

		if ( ! is_writable( $cache_dir ) ) {
			WP_CLI::debug( "Not restarting with OPcache: {$cache_dir} is not writable.", 'bootstrap' );
			return $state;
		}

		if ( OpcacheRestarter::is_file_cache_active() ) {
			WP_CLI::debug( 'Using OPcache\'s file cache in ' . ini_get( 'opcache.file_cache' ), 'bootstrap' );
		} else {
			WP_CLI::debug( "Restarting with OPcache's file cache in {$cache_dir}", 'bootstrap' );
		}

		// Restarts PHP and exits with the restarted process's exit code if the file cache
		// isn't used yet. In the restarted process, this clears the restart's environment
		// variables, so that processes WP-CLI launches can restart in turn.
		( new OpcacheRestarter( $cache_dir ) )->check();

		return $state;
	}
}
