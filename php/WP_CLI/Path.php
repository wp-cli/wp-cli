<?php

namespace WP_CLI;

/**
 * Path manipulation helper class.
 *
 * Provides a centralized collection of path-related utility methods for
 * working with filesystem paths in a cross-platform manner.
 *
 * @package WP_CLI
 */
class Path {

	/**
	 * File stream wrapper prefix for Phar archives.
	 *
	 * @var string
	 */
	const PHAR_STREAM_PREFIX = 'phar://';

	/**
	 * Regular expression pattern to match __FILE__ and __DIR__ constants.
	 *
	 * We try to be smart and only replace the constants when they are not within quotes.
	 * Regular expressions being stateless, this is probably not 100% correct for edge cases.
	 *
	 * @see https://regex101.com/r/9hXp5d/11
	 * @see https://stackoverflow.com/a/171499/933065
	 *
	 * @var string
	 */
	const FILE_DIR_PATTERN = '%(?>#.*?$)|(?>//.*?$)|(?>/\*.*?\*/)|(?>\'(?:(?=(\\\\?))\1.)*?\')|(?>"(?:(?=(\\\\?))\2.)*?")|(?<file>\b__FILE__\b)|(?<dir>\b__DIR__\b)%ms';

	/**
	 * Check if a certain path is within a Phar archive.
	 *
	 * If no path is provided, the function checks whether the current WP_CLI instance is
	 * running from within a Phar archive.
	 *
	 * @param string|null $path Optional. Path to check. Defaults to null, which checks WP_CLI_ROOT.
	 * @return bool Whether path is within a Phar archive.
	 */
	public static function inside_phar( $path = null ) {
		if ( null === $path ) {
			if ( ! defined( 'WP_CLI_ROOT' ) ) {
				return false;
			}

			$path = WP_CLI_ROOT;
		}

		return 0 === stripos( $path, self::PHAR_STREAM_PREFIX );
	}

	/**
	 * Determine whether a path is absolute.
	 *
	 * @param string $path
	 * @return bool
	 */
	public static function is_absolute( $path ) {
		// Empty path is not absolute.
		if ( '' === $path ) {
			return false;
		}

		// Windows drive letter + colon + slash or backslash.
		if ( preg_match( '#^[A-Z]:[\\\\/]#i', $path ) ) {
			return true;
		}

		// UNC path (\\Server\Share).
		if ( preg_match( '#^\\\\\\\\[^\\\\/]+[\\\\/][^\\\\/]+#', $path ) ) {
			return true;
		}

		// Unix root.
		return isset( $path[0] ) && '/' === $path[0];
	}

	/**
	 * Expand tilde (~) in path to home directory.
	 *
	 * Expands paths that start with ~ to the current user's home directory.
	 * Only handles the current user's home directory (not ~username patterns).
	 *
	 * @param string $path Path that may contain a tilde.
	 * @return string Path with tilde expanded to home directory, or unchanged if tilde not at start or followed by username.
	 */
	public static function expand_tilde( $path ) {
		if ( isset( $path[0] ) && '~' === $path[0] ) {
			$home = self::get_home_dir();
			// Only expand if we can determine the home directory.
			// Handle both "~" and "~/..." patterns (but not "~username").
			if ( ! empty( $home ) && ( 1 === strlen( $path ) || '/' === $path[1] ) ) {
				$path = $home . substr( $path, 1 );
			}
			// If followed by anything other than '/', or home is empty, leave it unchanged.
		}

		return $path;
	}

	/**
	 * Get the home directory.
	 *
	 * @return string
	 */
	public static function get_home_dir() {
		$home = getenv( 'HOME' );
		if ( ! $home ) {
			// In Windows $HOME may not be defined.
			$home = getenv( 'HOMEDRIVE' ) . getenv( 'HOMEPATH' );
		}

		return rtrim( $home, '/\\' );
	}

	/**
	 * Appends a trailing slash.
	 *
	 * @param string $value What to add the trailing slash to.
	 * @return string String with trailing slash added.
	 */
	public static function trailingslashit( $value ) {
		if ( ! is_string( $value ) ) {
			return '/';
		}

		return rtrim( $value, '/\\' ) . '/';
	}

	/**
	 * Check if a path is a PHP stream URL.
	 *
	 * @param string $path The resource path or URL.
	 * @return bool True if the path is a PHP stream URL, false otherwise.
	 */
	public static function is_stream( $path ) {
		$scheme_separator = strpos( $path, '://' );

		if ( false === $scheme_separator ) {
			return false;
		}

		$stream = strtolower( substr( $path, 0, $scheme_separator ) );

		return in_array( $stream, stream_get_wrappers(), true );
	}

	/**
	 * Normalize a filesystem path.
	 *
	 * On Windows systems, replaces backslashes with forward slashes
	 * and forces upper-case drive letters.
	 * Allows for two leading slashes for Windows network shares, but
	 * ensures that all other duplicate slashes are reduced to a single one.
	 * Ensures upper-case drive letters on Windows systems.
	 * Allows for PHP file wrappers.
	 *
	 * @param string $path Path to normalize.
	 * @return string Normalized path.
	 */
	public static function normalize( $path ) {
		$wrapper = '';
		if ( self::is_stream( $path ) ) {
			list( $wrapper, $path ) = explode( '://', $path, 2 );
			$wrapper               .= '://';
		}
		$path = str_replace( '\\', '/', $path );
		$path = (string) preg_replace( '|(?<=.)/+|', '/', $path );
		if ( ':' === substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}
		// Resolve single-dot path segments (e.g., /foo/./bar becomes /foo/bar).
		$path = (string) preg_replace( '#/(?:\./)+#', '/', $path );
		if ( '/.' === substr( $path, -2 ) ) {
			$path = substr( $path, 0, -1 );
		}
		// Resolve leading ./ (e.g., ./foo/bar becomes foo/bar).
		$path = (string) preg_replace( '#^(?:\./)+#', '', $path );
		// Collapse any duplicate slashes introduced by dot-segment resolution.
		$path = (string) preg_replace( '|(?<=.)/+|', '/', $path );
		return $wrapper . $path;
	}

	/**
	 * Get the file basename.
	 *
	 * @param string $path
	 * @param string $suffix
	 * @return string
	 */
	public static function basename( $path, $suffix = '' ) {
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.urlencode_urlencode -- Format required by wordpress.org API.
		return urldecode( \basename( str_replace( [ '%2F', '%5C' ], '/', urlencode( $path ) ), $suffix ) );
	}

	/**
	 * Get a Phar-safe version of a path.
	 *
	 * For paths inside a Phar, this strips the outer filesystem's location to
	 * reduce the path to what it needs to be within the Phar archive.
	 *
	 * Use the __FILE__ or __DIR__ constants as a starting point.
	 *
	 * @param string $path An absolute path that might be within a Phar.
	 * @return string A Phar-safe version of the path.
	 */
	public static function phar_safe( $path ) {
		if ( ! self::inside_phar() ) {
			return $path;
		}

		return str_replace(
			self::PHAR_STREAM_PREFIX . rtrim( WP_CLI_PHAR_PATH, '/' ) . '/',
			self::PHAR_STREAM_PREFIX,
			$path
		);
	}

	/**
	 * Replace magic constants in some PHP source code.
	 *
	 * Replaces the __FILE__ and __DIR__ magic constants with the values they are
	 * supposed to represent at runtime.
	 *
	 * @param string $source The PHP code to manipulate.
	 * @param string $path The path to use instead of the magic constants.
	 * @return string Adapted PHP code.
	 */
	public static function replace_path_consts( $source, $path ) {
		// Solve issue with Windows allowing single quotes in account names.
		$file = addslashes( $path );

		if ( file_exists( $file ) ) {
			$file = (string) realpath( $file );
		}

		$dir = dirname( $file );

		// Replace __FILE__ and __DIR__ constants with value of $file or $dir.
		return (string) preg_replace_callback(
			self::FILE_DIR_PATTERN,
			static function ( $matches ) use ( $file, $dir ) {
				if ( ! empty( $matches['file'] ) ) {
					return "'{$file}'";
				}

				if ( ! empty( $matches['dir'] ) ) {
					return "'{$dir}'";
				}

				return $matches[0];
			},
			$source
		);
	}
}
