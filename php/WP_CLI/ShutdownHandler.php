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
		// Ensure WordPress's fatal error handler is always enabled for WP-CLI
		WP_CLI::add_wp_hook(
			'wp_fatal_error_handler_enabled',
			static function () {
				return true;
			}
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
		if ( ! is_array( $error ) || ! isset( $error['file'], $error['line'], $error['message'] ) ) {
			return wp_strip_all_tags( $message );
		}

		$message = "\nThere has been a critical error on this website.";

		/**
		 * @var string $file
		 */
		$file = $error['file'];

		$plugin = self::identify_plugin( $file );
		$theme  = self::identify_theme( $file );
		$skip   = '--skip-plugins --skip-themes';
		if ( $plugin ) {
			$message .= "\n\nThis error may have been caused by the plugin {$plugin}.";
			$message .= "\nTo skip this plugin, run the command again with:";
			$message .= "\n  --skip-plugins={$plugin}";

			$skip = [ 'skip-plugins' => $plugin ];
		} elseif ( 'functions.php' === $theme ) {
			$message .= "\n\nAn unexpected functions.php file in the themes directory may have caused this internal server error.";

			// This error cannot be skipped with `--skip-themes`.
			return $message;
		} elseif ( $theme ) {
			$message .= "\n\nThis error may have been caused by the theme {$theme}.";
			$message .= "\nTo skip this theme, run the command again with:";
			$message .= "\n  --skip-themes={$theme}";

			$skip = [ 'skip-themes' => $theme ];
		} else {
			$message .= "\n\nThis error may have been caused by a theme or plugin.";
			$message .= "\nTo skip all plugins and themes, run the command again with:";
			$message .= "\n  --skip-plugins --skip-themes";
			$skip     = [
				'skip-plugins' => true,
				'skip-themes'  => true,
			];
		}

		if ( ! self::should_prompt_rerun() ) {
			return $message;
		}

		WP_CLI::add_wp_hook(
			'wp_die_handler',
			function () use ( $skip ) {
				return static function ( $wp_error ) use ( $skip ) {
					// @phpstan-ignore staticMethod.alreadyNarrowedType
					WP_CLI::error( $wp_error->get_error_message(), false );

					self::prompt_and_rerun( $skip );
				};
			}
		);

		return $message;
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
	 * Check if we should prompt the user to rerun the command.
	 *
	 * @return bool
	 */
	private static function should_prompt_rerun() {
		// Check environment variable WP_CLI_SKIP_PROMPT
		// If set to 'yes', automatically rerun; if 'no', don't prompt
		$skip_prompt = getenv( 'WP_CLI_SKIP_PROMPT' );

		if ( false !== $skip_prompt ) {
			return 'yes' !== $skip_prompt && 'no' !== $skip_prompt;
		}

		// Default: prompt the user
		return true;
	}

	/**
	 * Prompt the user to rerun the command with the skip flag.
	 *
	 * @param array<string, bool|string> $skip Skip flag(s) to append.
	 */
	private static function prompt_and_rerun( $skip ) {
		// Get environment variable to check default behavior
		$skip_prompt = getenv( 'WP_CLI_SKIP_PROMPT' );

		// If set to 'yes', automatically rerun without prompting
		if ( 'yes' === $skip_prompt ) {
			self::rerun_with_skip( $skip );
			return;
		}

		// If set to 'no', don't prompt at all
		if ( 'no' === $skip_prompt ) {
			return;
		}

		$skip_string = self::get_skip_string( $skip );

		try {
			WP_CLI::confirm( "\nWould you like to run the command again with $skip_string?" );
			self::rerun_with_skip( $skip );
		} catch ( \WP_CLI\ExitException $e ) {
			// User declined or Ctrl+C - exit gracefully
			WP_CLI::line( 'Command not rerun.' );
		}
	}

	/**
	 * Return a formatted --skip-[...] string.
	 *
	 * @param array<string, bool|string> $skip Skip flag(s) to append.
	 * @return string
	 */
	private static function get_skip_string( $skip ) {
		return implode(
			' ',
			array_map(
				static function ( $key, $value ) {
					return is_bool( $value ) ? "--$key" : "--$key=$value";
				},
				array_keys( $skip ),
				array_values( $skip )
			)
		);
	}

	/**
	 * Rerun the current command with the skip flag.
	 *
	 * @param array<string, bool|string> $skip Skip flag(s) to append.
	 */
	private static function rerun_with_skip( $skip ) {
		$runner = WP_CLI::get_runner();

		if ( ! $runner ) {
			return;
		}

		$args       = $runner->arguments;
		$assoc_args = $runner->assoc_args;

		foreach ( $skip as $skip_flag => $slug ) {
			if ( isset( $assoc_args[ $skip_flag ] ) && ! is_bool( $slug ) ) {
				// Add slug to existing skip list.
				$existing                  = $assoc_args[ $skip_flag ];
				$assoc_args[ $skip_flag ] .= ',' . $slug;
			} else {
				$assoc_args[ $skip_flag ] = $slug;
			}
		}

		$skip_string = self::get_skip_string( $skip );

		WP_CLI::line( "\nRerunning command with {$skip_string}...\n" );

		try {
			WP_CLI::run_command( $args, $assoc_args );
		} catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}
}
