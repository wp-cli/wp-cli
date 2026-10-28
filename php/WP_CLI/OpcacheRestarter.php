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
		if ( $xdebug_active ) {
			return false;
		}

		return ! self::is_file_cache_active();
	}

	/**
	 * Add the OPcache settings to the restart command.
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
	 * @param non-empty-list<string> $command PHP binary, its options, the script and its arguments.
	 * @return void
	 */
	protected function restart( array $command ): void {
		// The command starts with the PHP binary and `-n -c <tmp ini>`, followed by the script.
		array_splice(
			$command,
			4,
			0,
			[
				'-d',
				'opcache.enable_cli=1',
				'-d',
				'opcache.file_cache=' . $this->cache_dir,
				'-d',
				'opcache.file_cache_only=1',
				'-d',
				'opcache.save_comments=1',
				'-d',
				'opcache.validate_timestamps=1',
			]
		);

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
