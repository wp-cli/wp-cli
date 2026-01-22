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
		
		// Normalize path separators
		$file = str_replace( '\\', '/', $file );

		// Try to identify if the error is from a plugin
		$plugin = self::identify_plugin( $file );
		if ( $plugin ) {
			return self::format_plugin_suggestion( $plugin, $error );
		}

		// Try to identify if the error is from a theme
		$theme = self::identify_theme( $file );
		if ( $theme ) {
			return self::format_theme_suggestion( $theme, $error );
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
		// Check for wp-content/plugins pattern
		if ( preg_match( '#/wp-content/plugins/([^/]+)/#', $file, $matches ) ) {
			return $matches[1];
		}

		// Also check for mu-plugins
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
		// Check for wp-content/themes pattern
		if ( preg_match( '#/wp-content/themes/([^/]+)/#', $file, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Format a suggestion message for a plugin error.
	 *
	 * @param string                                              $plugin Plugin slug.
	 * @param array{type: int, message: string, file: string, line: int} $error  Error information.
	 * @return string Formatted suggestion message.
	 */
	private static function format_plugin_suggestion( $plugin, $error ) {
		$message  = "Error: A fatal error occurred";
		$message .= " in the '{$plugin}' plugin";
		$message .= ":\n";
		$message .= basename( $error['file'] ) . ':' . $error['line'] . ' - ' . $error['message'] . "\n";
		$message .= "\n";
		$message .= "To skip this plugin, run the command again with:\n";
		$message .= "  --skip-plugins={$plugin}";

		return $message;
	}

	/**
	 * Format a suggestion message for a theme error.
	 *
	 * @param string                                              $theme Theme slug.
	 * @param array{type: int, message: string, file: string, line: int} $error Error information.
	 * @return string Formatted suggestion message.
	 */
	private static function format_theme_suggestion( $theme, $error ) {
		$message  = "Error: A fatal error occurred";
		$message .= " in the '{$theme}' theme";
		$message .= ":\n";
		$message .= basename( $error['file'] ) . ':' . $error['line'] . ' - ' . $error['message'] . "\n";
		$message .= "\n";
		$message .= "To skip this theme, run the command again with:\n";
		$message .= "  --skip-themes={$theme}";

		return $message;
	}
}
