<?php

namespace WP_CLI;

use Composer\XdebugHandler\XdebugHandler;

/**
 * Restarts the PHP process with OPcache's file cache enabled.
 *
 * Even when OPcache is enabled for the CLI, it keeps compiled code in shared memory,
 * which only lasts for a single process. With the file cache, compiled code is stored on
 * disk and reused by the next run, so WP-CLI, WordPress and plugins don't have to be
 * compiled every time.
 *
 * The restart itself is done by composer/xdebug-handler, which keeps the PHP settings
 * of the current process, including `-d` options, and passes STDIN and STDOUT through.
 *
 * @package WP_CLI
 */
final class OpcacheRestarter extends XdebugHandler {

	/**
	 * Directory for OPcache's file cache.
	 *
	 * @var string
	 */
	private $cache_dir;

	/**
	 * @param string $cache_dir Directory for OPcache's file cache.
	 */
	public function __construct( $cache_dir ) {
		parent::__construct( 'WP_CLI' );

		$this->cache_dir = $cache_dir;
	}

	/**
	 * Restart only if OPcache's file cache isn't used yet.
	 *
	 * Processes with Xdebug active are left alone, as they are probably being debugged.
	 *
	 * @param bool $xdebug_active Whether Xdebug is active.
	 * @return bool
	 */
	protected function requiresRestart( bool $xdebug_active ): bool {
		if ( self::is_file_cache_active() ) {
			return false;
		}

		if ( $xdebug_active ) {
			\WP_CLI::debug( 'Not restarting with OPcache: Xdebug is active.', 'bootstrap' );
			return false;
		}

		\WP_CLI::debug( "Restarting with OPcache's file cache in {$this->cache_dir}", 'bootstrap' );
		return true;
	}

	/**
	 * Add the OPcache settings to the temporary ini file used by the restarted process.
	 *
	 * The restarted process keeps the other settings of the current one, including OPcache
	 * settings from `php.ini` that had no effect while OPcache was off for the CLI. These
	 * settings are therefore always set:
	 *
	 * - `opcache.file_cache_only`: Only the file cache is used, without shared memory. It's
	 *   faster for a single short-lived process, and a preload script configured for the web
	 *   server (`opcache.preload`) isn't run. JIT needs shared memory, so it's not used.
	 * - `opcache.save_comments`: Commands are defined by their doc comments.
	 * - `opcache.validate_timestamps`: Edited files have to be compiled again.
	 *
	 * They are appended to the temporary ini file, after xdebug-handler's own
	 * `opcache.enable_cli=0`, so they take precedence. This doesn't depend on the layout of the
	 * restart command, and keeps the cache path out of the process list.
	 *
	 * @param non-empty-list<string> $command PHP binary, its options, the script and its arguments.
	 * @return void
	 */
	protected function restart( array $command ): void {
		$settings = PHP_EOL
			. 'opcache.enable_cli=1' . PHP_EOL
			. 'opcache.file_cache="' . $this->cache_dir . '"' . PHP_EOL
			. 'opcache.file_cache_only=1' . PHP_EOL
			. 'opcache.save_comments=1' . PHP_EOL
			. 'opcache.validate_timestamps=1' . PHP_EOL;

		// xdebug-handler has already prepared the environment for the restart, so restart in any
		// case. Without the settings, the restarted process just doesn't use the file cache.
		$tmp_ini = $this->tmpIni; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Inherited from XdebugHandler.
		if ( ! is_string( $tmp_ini ) || ! is_writable( $tmp_ini ) || false === file_put_contents( $tmp_ini, $settings, FILE_APPEND ) ) {
			\WP_CLI::debug( 'Could not add the OPcache settings to the temporary ini file.', 'bootstrap' );
		}

		parent::restart( $command );
	}

	/**
	 * Whether OPcache's file cache is used by the current process.
	 *
	 * @return bool
	 */
	public static function is_file_cache_active() {
		return (bool) ini_get( 'opcache.enable_cli' ) && '' !== (string) ini_get( 'opcache.file_cache' );
	}
}
