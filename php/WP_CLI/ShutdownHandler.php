<?php

namespace WP_CLI;

use WP_CLI;

/**
 * Handles shutdown to detect incomplete execution and suggest workarounds.
 *
 * This handler detects when WP-CLI execution fails due to plugin or theme errors
 * and provides helpful suggestions to the user.
 *
 * @package WP_CLI
 */
class ShutdownHandler {

	/**
	 * Whether the command completed successfully.
	 *
	 * @var bool
	 */
	private static $command_completed = false;

	/**
	 * Whether WordPress has finished loading.
	 *
	 * @var bool
	 */
	private static $wp_loaded = false;

	/**
	 * Register the shutdown handler.
	 */
	public static function register() {
		register_shutdown_function( [ __CLASS__, 'handle_shutdown' ] );
		WP_CLI::add_hook( 'after_wp_load', [ __CLASS__, 'mark_wp_loaded' ] );
	}

	/**
	 * Mark that WordPress has finished loading.
	 */
	public static function mark_wp_loaded() {
		self::$wp_loaded = true;
	}

	/**
	 * Mark that the command completed successfully.
	 */
	public static function mark_command_completed() {
		self::$command_completed = true;
	}

	/**
	 * Handle the shutdown event.
	 */
	public static function handle_shutdown() {
		// If the command completed successfully, nothing to do
		if ( self::$command_completed ) {
			return;
		}

		// Only handle errors if WordPress was loading or loaded
		// (errors before WordPress loads are less likely to be plugin/theme related)
		if ( ! self::$wp_loaded ) {
			return;
		}

		// Get the last error
		$error = error_get_last();
		if ( null === $error ) {
			return;
		}

		// Only handle fatal errors
		$fatal_error_types = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ];
		if ( ! in_array( $error['type'], $fatal_error_types, true ) ) {
			return;
		}

		// Try to identify the problematic plugin or theme
		$suggestion = self::get_error_suggestion( $error );
		if ( $suggestion ) {
			// Output to STDERR since we're in shutdown
			fwrite( STDERR, "\n" . $suggestion . "\n" );
		}
	}

	/**
	 * Analyze the error and provide a helpful suggestion.
	 *
	 * @param array{type: int, message: string, file: string, line: int} $error Error information from error_get_last().
	 * @return string|null Suggestion message, or null if no suggestion available.
	 */
	private static function get_error_suggestion( $error ) {
		$file = $error['file'];

		// Try to identify if the error is from a plugin
		$plugin = self::identify_plugin( $file );
		if ( $plugin ) {
			return self::format_suggestion( 'plugin', $plugin, $error );
		}

		// Try to identify if the error is from a theme
		$theme = self::identify_theme( $file );
		if ( $theme ) {
			return self::format_suggestion( 'theme', $theme, $error );
		}

		return null;
	}

	/**
	 * Identify the plugin causing the error.
	 *
	 * @param string $file File path where error occurred.
	 * @return string|null Plugin slug, or null if not a plugin error.
	 */
	private static function identify_plugin( $file ) {
		// Normalize path separators for consistent matching
		$file = str_replace( '\\', '/', $file );

		// Use WordPress constants if available for more accurate path detection
		if ( defined( 'WP_PLUGIN_DIR' ) ) {
			$plugin_dir = str_replace( '\\', '/', WP_PLUGIN_DIR );
			if ( 0 === strpos( $file, $plugin_dir . '/' ) ) {
				$relative = substr( $file, strlen( $plugin_dir ) + 1 );
				$parts    = explode( '/', $relative );
				if ( ! empty( $parts[0] ) ) {
					// For plugins in subdirectories, return the directory name
					// For single-file plugins, return the filename without .php
					return false !== strpos( $parts[0], '.php' ) ? basename( $parts[0], '.php' ) : $parts[0];
				}
			}
		}

		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$mu_plugin_dir = str_replace( '\\', '/', WPMU_PLUGIN_DIR );
			if ( 0 === strpos( $file, $mu_plugin_dir . '/' ) ) {
				$relative = substr( $file, strlen( $mu_plugin_dir ) + 1 );
				$parts    = explode( '/', $relative );
				if ( ! empty( $parts[0] ) ) {
					// For mu-plugins in subdirectories, return the directory name
					// For single-file mu-plugins, return the filename without .php
					return false !== strpos( $parts[0], '.php' ) ? basename( $parts[0], '.php' ) : $parts[0];
				}
			}
		}

		// Fallback to pattern matching if constants are not available
		if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $file, $matches ) ) {
			return $matches[1];
		}

		// Check for direct single-file plugins
		if ( preg_match( '#/wp-content/plugins/([^/]+)\\.php$#', $file, $matches ) ) {
			return $matches[1];
		}
		// Check for mu-plugins in subdirectories
		if ( preg_match( '#/wp-content/mu-plugins/([^/]+)/#', $file, $matches ) ) {
			return $matches[1];
		}

		// Check for direct mu-plugin PHP files
		if ( preg_match( '#/wp-content/mu-plugins/([^/]+)\\.php$#', $file, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Identify the theme causing the error.
	 *
	 * @param string $file File path where error occurred.
	 * @return string|null Theme slug, or null if not a theme error.
	 */
	private static function identify_theme( $file ) {
		// Normalize path separators for consistent matching
		$file = str_replace( '\\', '/', $file );

		// Use get_theme_root() if available for more accurate path detection
		if ( function_exists( 'get_theme_root' ) ) {
			$theme_root = str_replace( '\\', '/', get_theme_root() );
			if ( 0 === strpos( $file, $theme_root . '/' ) ) {
				$relative = substr( $file, strlen( $theme_root ) + 1 );
				$parts    = explode( '/', $relative );
				if ( ! empty( $parts[0] ) ) {
					return $parts[0];
				}
			}
		} elseif ( defined( 'WP_CONTENT_DIR' ) ) {
			// Fallback to WP_CONTENT_DIR/themes if get_theme_root() is not available
			$theme_dir = str_replace( '\\', '/', WP_CONTENT_DIR ) . '/themes';
			if ( 0 === strpos( $file, $theme_dir . '/' ) ) {
				$relative = substr( $file, strlen( $theme_dir ) + 1 );
				$parts    = explode( '/', $relative );
				if ( ! empty( $parts[0] ) ) {
					return $parts[0];
				}
			}
		}

		// Fallback to pattern matching if constants/functions are not available
		if ( preg_match( '#/wp-content/themes/([^/]+)/#', $file, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Format a suggestion message for a component error.
	 *
	 * @param string                                              $type   Component type ('plugin' or 'theme').
	 * @param string                                              $slug   Component slug.
	 * @param array{type: int, message: string, file: string, line: int} $error  Error information.
	 * @return string Formatted suggestion message.
	 */
	private static function format_suggestion( $type, $slug, $error ) {
		// Normalize path for basename to work with Windows paths
		$normalized_file = str_replace( '\\', '/', $error['file'] );
		$message         = 'Error: A fatal error occurred';
		$message        .= " in the '{$slug}' {$type}";
		$message        .= ":\n";
		$message        .= basename( $normalized_file ) . ':' . $error['line'] . ' - ' . $error['message'] . "\n";
		$message        .= "\n";
		$message        .= "To skip this {$type}, run the command again with:\n";
		$message        .= "  --skip-{$type}s={$slug}";

		return $message;
	}
}
