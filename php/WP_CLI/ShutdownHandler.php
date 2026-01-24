<?php

namespace WP_CLI;

use WP_CLI;

/**
 * Handles fatal errors to detect plugin/theme issues and suggest workarounds.
 *
 * This handler hooks into WordPress's fatal error handler to provide
 * helpful suggestions to the user when plugins or themes cause errors.
 *
 * @package WP_CLI
 */
class ShutdownHandler {

	/**
	 * Register the error message filter.
	 */
	public static function register() {
		WP_CLI::add_hook( 'after_wp_load', [ __CLASS__, 'register_filters' ] );
	}

	/**
	 * Register WordPress filters after WordPress loads.
	 */
	public static function register_filters() {
		// Ensure WordPress's fatal error handler is always enabled for WP-CLI
		WP_CLI::add_wp_hook(
			'wp_fatal_error_handler_enabled',
			'__return_true'
		);

		// Hook into the error message filter to add our suggestions
		WP_CLI::add_wp_hook(
			'wp_php_error_message',
			[ __CLASS__, 'filter_error_message' ],
			10,
			2
		);
	}

	/**
	 * Filter the PHP error message to add plugin/theme skip suggestions.
	 *
	 * @param string $message Error message.
	 * @param array  $error   Error information from error_get_last().
	 * @return string Filtered error message.
	 */
	public static function filter_error_message( $message, $error ) {
		// Ensure $error is an array with required keys
		if ( ! is_array( $error ) || ! isset( $error['file'], $error['line'], $error['message'] ) ) {
			return wp_strip_all_tags( $message );
		}

		// Build a CLI-appropriate error message with stacktrace
		$cli_message = self::format_cli_error_message( $error );

		// Try to identify the problematic plugin or theme
		$suggestion = self::get_error_suggestion( $error );
		if ( $suggestion ) {
			$cli_message .= "\n\n" . $suggestion;
		}

		return $cli_message;
	}

	/**
	 * Format error information for CLI output.
	 *
	 * @param array $error Error information from error_get_last().
	 * @return string Formatted error message.
	 */
	private static function format_cli_error_message( $error ) {
		$error_type_labels = [
			E_ERROR             => 'Fatal error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parse error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core error',
			E_CORE_WARNING      => 'Core warning',
			E_COMPILE_ERROR     => 'Compile error',
			E_COMPILE_WARNING   => 'Compile warning',
			E_USER_ERROR        => 'User error',
			E_USER_WARNING      => 'User warning',
			E_USER_NOTICE       => 'User notice',
			E_STRICT            => 'Strict standards',
			E_RECOVERABLE_ERROR => 'Recoverable error',
			E_DEPRECATED        => 'Deprecated',
			E_USER_DEPRECATED   => 'User deprecated',
		];

		$error_type = isset( $error_type_labels[ $error['type'] ] )
			? $error_type_labels[ $error['type'] ]
			: 'Error';

		$message  = "PHP {$error_type}: {$error['message']}";
		$message .= "\n  in {$error['file']} on line {$error['line']}";

		// Add simplified stacktrace if available
		if ( function_exists( 'wp_debug_backtrace_summary' ) ) {
			$backtrace = wp_debug_backtrace_summary( __CLASS__ );
			if ( ! empty( $backtrace ) ) {
				$message .= "\n\nStack trace:\n  " . $backtrace;
			}
		}

		return $message;
	}

	/**
	 * Analyze the error and provide a helpful suggestion.
	 *
	 * @param array $error Error information from error_get_last().
	 * @return string|null Suggestion message, or null if no suggestion available.
	 */
	private static function get_error_suggestion( $error ) {
		$file = $error['file'];

		// Try to identify if the error is from a plugin
		$plugin = self::identify_plugin( $file );
		if ( $plugin ) {
			return self::format_suggestion( 'plugin', $plugin );
		}

		// Try to identify if the error is from a theme
		$theme = self::identify_theme( $file );
		if ( $theme ) {
			return self::format_suggestion( 'theme', $theme );
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
			$slug = self::extract_component_slug( $file, WP_PLUGIN_DIR );
			if ( $slug ) {
				return $slug;
			}
		}

		if ( defined( 'WPMU_PLUGIN_DIR' ) ) {
			$slug = self::extract_component_slug( $file, WPMU_PLUGIN_DIR );
			if ( $slug ) {
				return $slug;
			}
		}

		// Fallback to pattern matching if constants are not available
		if ( preg_match( '#/wp-content/(?:mu-)?plugins/([^/]+)/#', $file, $matches ) ) {
			return $matches[1];
		}

		// Check for direct single-file plugins
		if ( preg_match( '#/wp-content/(?:mu-)?plugins/([^/]+)\\.php$#', $file, $matches ) ) {
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
			$slug = self::extract_theme_slug( $file, get_theme_root() );
			if ( $slug ) {
				return $slug;
			}
		} elseif ( defined( 'WP_CONTENT_DIR' ) ) {
			// Fallback to WP_CONTENT_DIR/themes if get_theme_root() is not available
			$slug = self::extract_theme_slug( $file, WP_CONTENT_DIR . '/themes' );
			if ( $slug ) {
				return $slug;
			}
		}

		// Fallback to pattern matching if constants/functions are not available
		if ( preg_match( '#/wp-content/themes/([^/]+)/#', $file, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Extract component slug from a file path given a base directory.
	 *
	 * @param string $file    File path where error occurred.
	 * @param string $base_dir Base directory path.
	 * @return string|null Component slug, or null if not found.
	 */
	private static function extract_component_slug( $file, $base_dir ) {
		$base_dir = str_replace( '\\', '/', $base_dir );
		if ( 0 === strpos( $file, $base_dir . '/' ) ) {
			$relative = substr( $file, strlen( $base_dir ) + 1 );
			$parts    = explode( '/', $relative );
			if ( ! empty( $parts[0] ) ) {
				// For components in subdirectories, return the directory name
				// For single-file components, return the filename without .php
				return false !== strpos( $parts[0], '.php' ) ? basename( $parts[0], '.php' ) : $parts[0];
			}
		}
		return null;
	}

	/**
	 * Extract theme slug from a file path given a theme directory.
	 *
	 * @param string $file      File path where error occurred.
	 * @param string $theme_dir Theme directory path.
	 * @return string|null Theme slug, or null if not found.
	 */
	private static function extract_theme_slug( $file, $theme_dir ) {
		$theme_dir = str_replace( '\\', '/', $theme_dir );
		if ( 0 === strpos( $file, $theme_dir . '/' ) ) {
			$relative = substr( $file, strlen( $theme_dir ) + 1 );
			$parts    = explode( '/', $relative );
			if ( ! empty( $parts[0] ) ) {
				return $parts[0];
			}
		}
		return null;
	}

	/**
	 * Format a suggestion message for a component error.
	 *
	 * @param string $type Component type ('plugin' or 'theme').
	 * @param string $slug Component slug.
	 * @return string Formatted suggestion message.
	 */
	private static function format_suggestion( $type, $slug ) {
		$message  = "This error may have been caused by the '{$slug}' {$type}.";
		$message .= "\nTo skip this {$type}, run the command again with:";
		$message .= "\n  --skip-{$type}s={$slug}";

		return $message;
	}
}
