<?php

namespace WP_CLI;

use InvalidArgumentException;
use RuntimeException;

/**
 * Contains utility methods for handling path strings.
 *
 * The methods in this class are able to deal with both UNIX and Windows paths
 * with both forward and backward slashes. All methods return normalized parts
 * containing only forward slashes and no excess "." and ".." segments.
 *
 * Most of the code in this class was copied from or based on code in the
 * webmozart/path-util package (c) Bernhard Schussek <bschussek@gmail.com>.
 *
 * @author Bernhard Schussek <bschussek@gmail.com>
 * @author Thomas Schulz <mail@king2500.net>
 */
final class Path {

	const PHAR_STREAM_PREFIX = 'phar://';

	/**
	 * The number of buffer entries that triggers a cleanup operation.
	 */
	const CLEANUP_THRESHOLD = 1250;

	/**
	 * The buffer size after the cleanup operation.
	 */
	const CLEANUP_SIZE = 1000;

	/**
	 * Buffers input/output of {@link canonicalize()}.
	 *
	 * @var array
	 */
	private static $buffer = array();

	/**
	 * The size of the buffer.
	 *
	 * @var int
	 */
	private static $buffer_size = 0;

	/**
	 * Canonicalizes the given path.
	 *
	 * During normalization, all slashes are replaced by forward slashes ("/").
	 * Furthermore, all "." and ".." segments are removed as far as possible.
	 * ".." segments at the beginning of relative paths are not removed.
	 *
	 * ```php
	 * echo Path::canonicalize("\webmozart\puli\..\css\style.css");
	 * // => /webmozart/css/style.css
	 *
	 * echo Path::canonicalize("../css/./style.css");
	 * // => ../css/style.css
	 * ```
	 *
	 * This method is able to deal with both UNIX and Windows paths.
	 *
	 * @param string $path A path string.
	 *
	 * @return string The canonical path.
	 */
	public static function canonicalize( $path ) {
		if ( '' === $path ) {
			return '';
		}

		self::assert_string( $path, 'The path must be a string. Got: %s' );

		// This method is called by many other methods in this class. Buffer
		// the canonicalized paths to make up for the severe performance
		// decrease.
		if ( isset( self::$buffer[ $path ] ) ) {
			return self::$buffer[ $path ];
		}

		// Replace "~" with user's home directory.
		$path = (string) preg_replace_callback(
			'~^\~(?<user>[^/\s]+?)?(?=/|$)~',
			function ( $matches ) {
				$user = array_key_exists( 'user', $matches )
					? $matches['user']
					: null;
				return self::get_home_directory( $user );
			},
			$path
		);

		$path = self::normalize( $path );

		list( $root, $path_without_root ) = self::split( $path );

		$parts           = explode( '/', $path_without_root );
		$canonical_parts = array();

		// Collapse "." and "..", if possible
		foreach ( $parts as $part ) {
			if ( '.' === $part || '' === $part ) {
				continue;
			}

			// Collapse ".." with the previous part, if one exists
			// Don't collapse ".." if the previous part is also ".."
			if ( '..' === $part && count( $canonical_parts ) > 0
				&& '..' !== $canonical_parts[ count( $canonical_parts ) - 1 ] ) {
				array_pop( $canonical_parts );

				continue;
			}

			// Only add ".." prefixes for relative paths
			if ( '..' !== $part || '' === $root ) {
				$canonical_parts[] = $part;
			}
		}

		// Add the root directory again
		$canonical_path        = $root . implode( '/', $canonical_parts );
		self::$buffer[ $path ] = $canonical_path;
		++ self::$buffer_size;

		// Clean up regularly to prevent memory leaks
		if ( self::$buffer_size > self::CLEANUP_THRESHOLD ) {
			self::$buffer      = array_slice(
				self::$buffer,
				- self::CLEANUP_SIZE,
				null,
				true
			);
			self::$buffer_size = self::CLEANUP_SIZE;
		}

		return $canonical_path;
	}

	/**
	 * Normalizes the given path.
	 *
	 * During normalization, all slashes are replaced by forward slashes ("/").
	 * Contrary to {@link canonicalize()}, this method does not remove invalid
	 * or dot path segments. Consequently, it is much more efficient and should
	 * be used whenever the given path is known to be a valid, absolute system
	 * path.
	 *
	 * This method is able to deal with both UNIX and Windows paths.
	 *
	 * @param string $path A path string.
	 *
	 * @return string The normalized path.
	 */
	public static function normalize( $path ) {
		self::assert_string( $path, 'The path must be a string. Got: %s' );

		$wrapper = '';
		if ( self::is_stream( $path ) ) {
			list( $wrapper, $path ) = explode( '://', $path, 2 );
			$wrapper               .= '://';
		}

		// Standardise all paths to use /
		$path = str_replace( '\\', '/', $path );

		// Replace multiple slashes down to a singular, allowing for network shares having two slashes.
		$path = preg_replace( '|(?<=.)/+|', '/', $path );

		// Windows paths should uppercase the drive letter
		if ( ':' === substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}

		return "{$wrapper}{$path}";
	}

	/**
	 * Returns the directory part of the path.
	 *
	 * This method is similar to PHP's dirname(), but handles various cases
	 * where dirname() returns a weird result:
	 *
	 *  - dirname() does not accept backslashes on UNIX
	 *  - dirname("C:/webmozart") returns "C:", not "C:/"
	 *  - dirname("C:/") returns ".", not "C:/"
	 *  - dirname("C:") returns ".", not "C:/"
	 *  - dirname("webmozart") returns ".", not ""
	 *  - dirname() does not canonicalize the result
	 *
	 * This method fixes these shortcomings and behaves like dirname()
	 * otherwise.
	 *
	 * The result is a canonical path.
	 *
	 * @param string $path A path string.
	 *
	 * @return string The canonical directory part. Returns the root directory
	 *                if the root directory is passed. Returns an empty string
	 *                if a relative path is passed that contains no slashes.
	 *                Returns an empty string if an empty string is passed.
	 */
	public static function get_directory( $path ) {
		if ( '' === $path ) {
			return '';
		}

		$path = self::canonicalize( $path );

		// Maintain scheme
		if ( false !== ( $pos = strpos( $path, '://' ) ) ) {
			$scheme = substr( $path, 0, $pos + 3 );
			$path   = substr( $path, $pos + 3 );
		} else {
			$scheme = '';
		}

		if ( false !== ( $pos = strrpos( $path, '/' ) ) ) {
			// Directory equals root directory "/"
			if ( 0 === $pos ) {
				return $scheme . '/';
			}

			// Directory equals Windows root "C:/"
			if ( 2 === $pos && ctype_alpha( $path[0] ) && ':' === $path[1] ) {
				return $scheme . substr( $path, 0, 3 );
			}

			return $scheme . substr( $path, 0, $pos );
		}

		return '';
	}

	/**
	 * Returns canonical path of the user's home directory.
	 *
	 * Supported operating systems:
	 *
	 *  - UNIX
	 *  - Windows8 and upper
	 *
	 * If your operation system or environment isn't supported, an exception is
	 * thrown.
	 *
	 * The result is a canonical path.
	 *
	 * @param string $user Optional. User to retrieve the home folder for.
	 *
	 * @return string The canonical home directory
	 *
	 * @throws RuntimeException If your operation system or environment isn't
	 *                          supported
	 */
	public static function get_home_directory( $user = null ) {
		if ( getenv( 'HOME' ) ) {
			// For UNIX support
			$home = self::canonicalize( getenv( 'HOME' ) );
		} elseif ( getenv( 'HOMEDRIVE' ) && getenv( 'HOMEPATH' ) ) {
			// For >= Windows8 support
			$home = self::canonicalize( getenv( 'HOMEDRIVE' ) . getenv( 'HOMEPATH' ) );
		}

		if ( ! isset( $home ) ) {
			throw new RuntimeException( 'Your environment or operation system isn\'t supported' );
		}

		if ( ! empty( $user ) ) {
			$home = self::join( self::get_directory( $home ), $user );
		}

		return $home;
	}

	/**
	 * Returns the root directory of a path.
	 *
	 * The result is a canonical path.
	 *
	 * @param string $path A path string.
	 *
	 * @return string The canonical root directory. Returns an empty string if
	 *                the given path is relative or empty.
	 */
	public static function get_root( $path ) {
		if ( '' === $path ) {
			return '';
		}

		self::assert_string( $path, 'The path must be a string. Got: %s' );

		// Maintain scheme
		if ( false !== ( $pos = strpos( $path, '://' ) ) ) {
			$scheme = substr( $path, 0, $pos + 3 );
			$path   = substr( $path, $pos + 3 );
		} else {
			$scheme = '';
		}

		// UNIX root "/" or "\" (Windows style)
		if ( '/' === $path[0] || '\\' === $path[0] ) {
			return $scheme . '/';
		}

		$length = strlen( $path );

		// Windows root
		if ( $length > 1 && ctype_alpha( $path[0] ) && ':' === $path[1] ) {
			// Special case: "C:"
			if ( 2 === $length ) {
				return $scheme . $path . '/';
			}

			// Normal case: "C:/ or "C:\"
			if ( '/' === $path[2] || '\\' === $path[2] ) {
				return $scheme . $path[0] . $path[1] . '/';
			}
		}

		return '';
	}

	/**
	 * Returns the file name from a file path.
	 *
	 * @param string $path The path string.
	 *
	 * @return string The file name.
	 */
	public static function get_filename( $path ) {
		if ( '' === $path ) {
			return '';
		}

		self::assert_string( $path, 'The path must be a string. Got: %s' );

		return basename( $path );
	}

	/**
	 * Returns the file name without the extension from a file path.
	 *
	 * @param string      $path      The path string.
	 * @param string|null $extension If specified, only that extension is cut
	 *                               off (may contain leading dot).
	 *
	 * @return string The file name without extension.
	 */
	public static function get_filename_without_extension( $path, $extension = null ) {
		if ( '' === $path ) {
			return '';
		}

		self::assert_string( $path, 'The path must be a string. Got: %s' );
		self::assert_optional_string(
			$extension,
			'The extension must be a string or null. Got: %s'
		);

		if ( null !== $extension ) {
			// remove extension and trailing dot
			return rtrim( basename( $path, $extension ), '.' );
		}

		return pathinfo( $path, PATHINFO_FILENAME );
	}

	/**
	 * Returns the extension from a file path.
	 *
	 * @param string $path             The path string.
	 * @param bool   $force_lower_case Forces the extension to be lower-case
	 *                                 (requires mbstring extension for correct
	 *                                 multi-byte character handling in
	 *                                 extension).
	 *
	 * @return string The extension of the file path (without leading dot).
	 */
	public static function get_extension( $path, $force_lower_case = false ) {
		if ( '' === $path ) {
			return '';
		}

		self::assert_string( $path, 'The path must be a string. Got: %s' );

		$extension = pathinfo( $path, PATHINFO_EXTENSION );

		if ( $force_lower_case ) {
			$extension = self::to_lower( $extension );
		}

		return $extension;
	}

	/**
	 * Returns whether the path has an extension.
	 *
	 * @param string            $path        The path string.
	 * @param string|array|null $extensions  If null or not provided, checks if
	 *                                       an extension exists, otherwise
	 *                                       checks for the specified extension
	 *                                       or array of extensions (with or
	 *                                       without leading dot).
	 * @param bool              $ignore_case Whether to ignore case-sensitivity
	 *                                       (requires mbstring extension for
	 *                                       correct multi-byte character
	 *                                       handling in the extension).
	 *
	 * @return bool Returns `true` if the path has an (or the specified)
	 *              extension and `false` otherwise.
	 */
	public static function has_extension( $path, $extensions = null, $ignore_case = false ) {
		if ( '' === $path ) {
			return false;
		}

		$extensions = is_object( $extensions ) ? array( $extensions ) : (array) $extensions;

		self::assert_all_strings(
			$extensions,
			'The extensions must be strings. Got: %s'
		);

		$actual_extension = self::get_extension( $path, $ignore_case );

		// Only check if path has any extension
		if ( empty( $extensions ) ) {
			return '' !== $actual_extension;
		}

		foreach ( $extensions as $key => $extension ) {
			if ( $ignore_case ) {
				$extension = self::to_lower( $extension );
			}

			// remove leading '.' in extensions array
			$extensions[ $key ] = ltrim( $extension, '.' );
		}

		return in_array( $actual_extension, $extensions, true );
	}

	/**
	 * Changes the extension of a path string.
	 *
	 * @param string $path      The path string with filename.ext to change.
	 * @param string $extension New extension (with or without leading dot).
	 *
	 * @return string The path string with new file extension.
	 */
	public static function change_extension( $path, $extension ) {
		if ( '' === $path ) {
			return '';
		}

		self::assert_string(
			$extension,
			'The extension must be a string. Got: %s'
		);

		$actual_extension = self::get_extension( $path );
		$extension        = ltrim( $extension, '.' );

		// No extension for paths
		if ( '/' === substr( $path, - 1 ) ) {
			return $path;
		}

		// No actual extension in path
		if ( empty( $actual_extension ) ) {
			return $path
				. ( '.' === substr( $path, - 1 ) ? '' : '.' )
				. $extension;
		}

		return substr( $path, 0, - strlen( $actual_extension ) ) . $extension;
	}

	/**
	 * Returns whether a path is absolute.
	 *
	 * @param string $path A path string.
	 *
	 * @return bool Returns true if the path is absolute, false if it is
	 *              relative or empty.
	 */
	public static function is_absolute( $path ) {
		if ( '' === $path ) {
			return false;
		}

		self::assert_string( $path, 'The path must be a string. Got: %s' );

		// Strip scheme
		if ( false !== ( $pos = strpos( $path, '://' ) ) ) {
			$path = substr( $path, $pos + 3 );
		}

		// UNIX root "/" or "\" (Windows style)
		if ( '/' === $path[0] || '\\' === $path[0] ) {
			return true;
		}

		// Windows root
		if ( strlen( $path ) > 1 && ctype_alpha( $path[0] ) && ':' === $path[1] ) {
			// Special case: "C:"
			if ( 2 === strlen( $path ) ) {
				return true;
			}

			// Normal case: "C:/ or "C:\"
			if ( '/' === $path[2] || '\\' === $path[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Returns whether a path is relative.
	 *
	 * @param string $path A path string.
	 *
	 * @return bool Returns true if the path is relative or empty, false if
	 *              it is absolute.
	 */
	public static function is_relative( $path ) {
		return ! self::is_absolute( $path );
	}

	/**
	 * Turns a relative path into an absolute path.
	 *
	 * Usually, the relative path is appended to the given base path. Dot
	 * segments ("." and "..") are removed/collapsed and all slashes turned
	 * into forward slashes.
	 *
	 * ```php
	 * echo Path::make_absolute("../style.css", "/webmozart/puli/css");
	 * // => /webmozart/puli/style.css
	 * ```
	 *
	 * If an absolute path is passed, that path is returned unless its root
	 * directory is different than the one of the base path. In that case, an
	 * exception is thrown.
	 *
	 * ```php
	 * Path::make_absolute("/style.css", "/webmozart/puli/css");
	 * // => /style.css
	 *
	 * Path::make_absolute("C:/style.css", "C:/webmozart/puli/css");
	 * // => C:/style.css
	 *
	 * Path::make_absolute("C:/style.css", "/webmozart/puli/css");
	 * // InvalidArgumentException
	 * ```
	 *
	 * If the base path is not an absolute path, an exception is thrown.
	 *
	 * The result is a canonical path.
	 *
	 * @param string $path      A path to make absolute.
	 * @param string $base_path An absolute base path.
	 *
	 * @return string An absolute path in canonical form.
	 *
	 * @throws InvalidArgumentException If the base path is not absolute or if
	 *                                  the given path is an absolute path with
	 *                                  a different root than the base path.
	 */
	public static function make_absolute( $path, $base_path ) {
		self::assert_nonempty_string(
			$base_path,
			'The base path must be a non-empty string. Got: %s'
		);

		if ( ! self::is_absolute( $base_path ) ) {
			throw new InvalidArgumentException(
				sprintf(
					'The base path "%s" is not an absolute path.',
					$base_path
				)
			);
		}

		if ( self::is_absolute( $path ) ) {
			return self::canonicalize( $path );
		}

		if ( false !== ( $pos = strpos( $base_path, '://' ) ) ) {
			$scheme    = substr( $base_path, 0, $pos + 3 );
			$base_path = substr( $base_path, $pos + 3 );
		} else {
			$scheme = '';
		}

		return $scheme . self::canonicalize(
			rtrim( $base_path, '/\\' ) . '/' . $path
		);
	}

	/**
	 * Turns a path into a relative path.
	 *
	 * The relative path is created relative to the given base path:
	 *
	 * ```php
	 * echo Path::make_relative("/webmozart/style.css", "/webmozart/puli");
	 * // => ../style.css
	 * ```
	 *
	 * If a relative path is passed and the base path is absolute, the relative
	 * path is returned unchanged:
	 *
	 * ```php
	 * Path::make_relative("style.css", "/webmozart/puli/css");
	 * // => style.css
	 * ```
	 *
	 * If both paths are relative, the relative path is created with the
	 * assumption that both paths are relative to the same directory:
	 *
	 * ```php
	 * Path::make_relative("style.css", "webmozart/puli/css");
	 * // => ../../../style.css
	 * ```
	 *
	 * If both paths are absolute, their root directory must be the same,
	 * otherwise an exception is thrown:
	 *
	 * ```php
	 * Path::make_relative("C:/webmozart/style.css", "/webmozart/puli");
	 * // InvalidArgumentException
	 * ```
	 *
	 * If the passed path is absolute, but the base path is not, an exception
	 * is thrown as well:
	 *
	 * ```php
	 * Path::make_relative("/webmozart/style.css", "webmozart/puli");
	 * // InvalidArgumentException
	 * ```
	 *
	 * If the base path is not an absolute path, an exception is thrown.
	 *
	 * The result is a canonical path.
	 *
	 * @param string $path      A path to make relative.
	 * @param string $base_path A base path.
	 *
	 * @return string A relative path in canonical form.
	 *
	 * @throws InvalidArgumentException If the base path is not absolute or if
	 *                                  the given path has a different root
	 *                                  than the base path.
	 */
	public static function make_relative( $path, $base_path ) {
		self::assert_string(
			$base_path,
			'The base path must be a string. Got: %s'
		);

		$path      = self::canonicalize( $path );
		$base_path = self::canonicalize( $base_path );

		list( $root, $relative_path )           = self::split( $path );
		list( $base_root, $relative_base_path ) = self::split( $base_path );

		// If the base path is given as absolute path and the path is already
		// relative, consider it to be relative to the given absolute path
		// already
		if ( '' === $root && '' !== $base_root ) {
			// If base path is already in its root
			if ( '' === $relative_base_path ) {
				$relative_path = ltrim( $relative_path, './\\' );
			}

			return $relative_path;
		}

		// If the passed path is absolute, but the base path is not, we
		// cannot generate a relative path
		if ( '' !== $root && '' === $base_root ) {
			throw new InvalidArgumentException(
				sprintf(
					'The absolute path "%s" cannot be made relative to the ' .
					'relative path "%s". You should provide an absolute base ' .
					'path instead.',
					$path,
					$base_path
				)
			);
		}

		// Fail if the roots of the two paths are different
		if ( $base_root && $root !== $base_root ) {
			throw new InvalidArgumentException(
				sprintf(
					'The path "%s" cannot be made relative to "%s", because they ' .
					'have different roots ("%s" and "%s").',
					$path,
					$base_path,
					$root,
					$base_root
				)
			);
		}

		if ( '' === $relative_base_path ) {
			return $relative_path;
		}

		// Build a "../../" prefix with as many "../" parts as necessary
		$parts          = explode( '/', $relative_path );
		$base_parts     = explode( '/', $relative_base_path );
		$dot_dot_prefix = '';

		// Once we found a non-matching part in the prefix, we need to add
		// "../" parts for all remaining parts
		$match = true;

		foreach ( $base_parts as $i => $basePart ) {
			if ( $match && isset( $parts[ $i ] ) && $basePart === $parts[ $i ] ) {
				unset( $parts[ $i ] );

				continue;
			}

			$match           = false;
			$dot_dot_prefix .= '../';
		}

		return rtrim( $dot_dot_prefix . implode( '/', $parts ), '/' );
	}

	/**
	 * Returns whether the given path is on the local filesystem.
	 *
	 * @param string $path A path string.
	 *
	 * @return bool Returns true if the path is local, false for a URL.
	 */
	public static function is_local( $path ) {
		self::assert_string( $path, 'The path must be a string. Got: %s' );

		return '' !== $path && false === strpos( $path, '://' );
	}

	/**
	 * Returns the longest common base path of a set of paths.
	 *
	 * Dot segments ("." and "..") are removed/collapsed and all slashes turned
	 * into forward slashes.
	 *
	 * ```php
	 * $basePath = Path::get_longest_common_base_path(array(
	 *     '/webmozart/css/style.css',
	 *     '/webmozart/css/..'
	 * ));
	 * // => /webmozart
	 * ```
	 *
	 * The root is returned if no common base path can be found:
	 *
	 * ```php
	 * $basePath = Path::get_longest_common_base_path(array(
	 *     '/webmozart/css/style.css',
	 *     '/puli/css/..'
	 * ));
	 * // => /
	 * ```
	 *
	 * If the paths are located on different Windows partitions, `null` is
	 * returned.
	 *
	 * ```php
	 * $basePath = Path::get_longest_common_base_path(array(
	 *     'C:/webmozart/css/style.css',
	 *     'D:/webmozart/css/..'
	 * ));
	 * // => null
	 * ```
	 *
	 * @param array $paths A list of paths.
	 *
	 * @return string|null The longest common base path in canonical form or
	 *                     `null` if the paths are on different Windows
	 *                     partitions.
	 */
	public static function get_longest_common_base_path( array $paths ) {
		self::assert_all_strings(
			$paths,
			'The paths must be strings. Got: %s'
		);

		list( $bp_root, $base_path ) = self::split( self::canonicalize( reset( $paths ) ) );

		for ( next( $paths ); null !== key( $paths ) && '' !== $base_path; next( $paths ) ) {
			list( $root, $path ) = self::split( self::canonicalize( current( $paths ) ) );

			// If we deal with different roots (e.g. C:/ vs. D:/), it's time
			// to quit
			if ( $root !== $bp_root ) {
				return null;
			}

			// Make the base path shorter until it fits into path
			while ( true ) {
				if ( '.' === $base_path ) {
					// No more base paths
					$base_path = '';

					// Next path
					continue 2;
				}

				// Prevent false positives for common prefixes
				// see is_base_path()
				if ( 0 === strpos( $path . '/', $base_path . '/' ) ) {
					// Next path
					continue 2;
				}

				$base_path = dirname( $base_path );
			}
		}

		return $bp_root . $base_path;
	}

	/**
	 * Joins two or more path strings.
	 *
	 * The result is a canonical path.
	 *
	 * @param string[]|string $paths Path parts as parameters or array.
	 *
	 * @return string The joint path.
	 */
	public static function join( $paths ) {
		if ( ! is_array( $paths ) ) {
			$paths = func_get_args();
		}

		self::assert_all_strings(
			$paths,
			'The paths must be strings. Got: %s'
		);

		$final_path = null;
		$was_scheme = false;

		foreach ( $paths as $path ) {
			$path = (string) $path;

			if ( '' === $path ) {
				continue;
			}

			if ( null === $final_path ) {
				// For first part we keep slashes, like '/top', 'C:\' or 'phar://'
				$final_path = $path;
				$was_scheme = ( strpos( $path, '://' ) !== false );
				continue;
			}

			// Only add slash if previous part didn't end with '/' or '\'
			if ( ! in_array(
				substr( $final_path, - 1 ),
				array( '/', '\\' ),
				$strict = true
			) ) {
				$final_path .= '/';
			}

			// If first part included a scheme like 'phar://' we allow current part to start with '/', otherwise trim
			$final_path .= $was_scheme ? $path : ltrim( $path, '/' );
			$was_scheme  = false;
		}

		if ( null === $final_path ) {
			return '';
		}

		return self::canonicalize( $final_path );
	}

	/**
	 * Returns whether a path is a base path of another path.
	 *
	 * Dot segments ("." and "..") are removed/collapsed and all slashes turned
	 * into forward slashes.
	 *
	 * ```php
	 * Path::is_base_path('/webmozart', '/webmozart/css');
	 * // => true
	 *
	 * Path::is_base_path('/webmozart', '/webmozart');
	 * // => true
	 *
	 * Path::is_base_path('/webmozart', '/webmozart/..');
	 * // => false
	 *
	 * Path::is_base_path('/webmozart', '/puli');
	 * // => false
	 * ```
	 *
	 * @param string $base_path The base path to test.
	 * @param string $of_path   The other path.
	 *
	 * @return bool Whether the base path is a base path of the other path.
	 */
	public static function is_base_path( $base_path, $of_path ) {
		self::assert_string(
			$base_path,
			'The base path must be a string. Got: %s'
		);

		$base_path = self::canonicalize( $base_path );
		$of_path   = self::canonicalize( $of_path );

		// Append slashes to prevent false positives when two paths have
		// a common prefix, for example /base/foo and /base/foobar.
		// Don't append a slash for the root "/", because then that root
		// won't be discovered as common prefix ("//" is not a prefix of
		// "/foobar/").
		return 0 === strpos( $of_path . '/', rtrim( $base_path, '/' ) . '/' );
	}

	/**
	 * Add a trailing slash to a path if it doesn't contain one yet.
	 *
	 * @param string $path Path to add a trailing slash to.
	 * @return string Modified path.
	 */
	public static function ensure_trailing_slash( $path ) {
		self::assert_nonempty_string(
			$path,
			'The path must be a non-empty string. Got: %s'
		);

		// Only add slash if it doesn't already end in /, \ or :.
		if ( ! in_array(
			substr( $path, - 1 ),
			array( '/', '\\', ':' ),
			$strict = true
		) ) {
			$path .= self::guess_separator( $path );
		}

		return $path;
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
	 *
	 * @return string A Phar-safe version of the path.
	 */
	public function phar_safe( $path ) {

		if ( ! Utils\inside_phar() ) {
			return $path;
		}

		return self::canonicalize(
			str_replace(
				self::join( self::PHAR_STREAM_PREFIX, WP_CLI_PHAR_PATH ),
				self::PHAR_STREAM_PREFIX,
				$path
			)
		);
	}

	/**
	 * Returns whether a given path points to a stream.
	 *
	 * @param string $path Path to check.
	 *
	 * @return bool Whether the given path points to a stream.
	 */
	public static function is_stream( $path ) {
		$scheme_separator = strpos( $path, '://' );

		if ( false === $scheme_separator ) {
			// The $path doesn't match the basic format of a stream.
			return false;
		}

		$stream = substr( $path, 0, $scheme_separator );

		return in_array( $stream, stream_get_wrappers(), true );
	}

	/**
	 * Tries to guess the path separator that is used in a given path.
	 *
	 * Falls back to PHP default of '/' if inconclusive.
	 *
	 * @param string $path Path to guess the separator from.
	 *
	 * @return string Separator to use.
	 */
	public static function guess_separator( $path ) {
		if ( false !== strpos( $path, '\\' ) ) {
			if ( false === strpos( $path, '/' ) ) {
				return '\\';
			}
		}

		return '/';
	}

	/**
	 * Splits a part into its root directory and the remainder.
	 *
	 * If the path has no root directory, an empty root directory will be
	 * returned.
	 *
	 * If the root directory is a Windows style partition, the resulting root
	 * will always contain a trailing slash.
	 *
	 * list ($root, $path) = Path::split("C:/webmozart")
	 * // => array("C:/", "webmozart")
	 *
	 * list ($root, $path) = Path::split("C:")
	 * // => array("C:/", "")
	 *
	 * @param string $path The canonical path to split.
	 *
	 * @return string[] An array with the root directory and the remaining
	 *                  relative path.
	 */
	private static function split( $path ) {
		if ( '' === $path ) {
			return array( '', '' );
		}

		// Remember scheme as part of the root, if any
		if ( false !== ( $pos = strpos( $path, '://' ) ) ) {
			$root = substr( $path, 0, $pos + 3 );
			$path = substr( $path, $pos + 3 );
		} else {
			$root = '';
		}

		$length = strlen( $path );

		// Remove and remember root directory
		if ( '/' === $path[0] ) {
			$root .= '/';
			$path  = $length > 1 ? substr( $path, 1 ) : '';
		} elseif ( $length > 1 && ctype_alpha( $path[0] ) && ':' === $path[1] ) {
			if ( 2 === $length ) {
				// Windows special case: "C:"
				$root .= $path . '/';
				$path  = '';
			} elseif ( '/' === $path[2] ) {
				// Windows normal case: "C:/"..
				$root .= substr( $path, 0, 3 );
				$path  = $length > 3 ? substr( $path, 3 ) : '';
			}
		}

		return array( $root, $path );
	}

	/**
	 * Converts string to lower-case (multi-byte safe if mbstring is installed).
	 *
	 * @param string $str The string
	 *
	 * @return string Lower case string
	 */
	private static function to_lower( $str ) {
		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $str, mb_detect_encoding( $str ) );
		}

		return strtolower( $str );
	}

	private function __construct() {
	}

	/**
	 * Assert that the given argument is of type string.
	 *
	 * @param mixed  $argument Argument to assert the type of.
	 * @param string $message  Optional. The message to use for the exception.
	 *                         The message string should contain a placeholder
	 *                         for sprintf to insert the actual type into.
	 */
	private static function assert_string( $argument, $message = 'Argument must be of type string, %s provided.' ) {
		if ( ! is_string( $argument ) ) {
			throw new InvalidArgumentException(
				sprintf(
					$message,
					is_object( $argument )
					? get_class( $argument )
					: gettype( $argument )
				)
			);
		}
	}

	/**
	 * Assert that the given argument is of type string or null.
	 *
	 * @param mixed  $argument Argument to assert the type of.
	 * @param string $message  Optional. The message to use for the exception.
	 *                         The message string should contain a placeholder
	 *                         for sprintf to insert the actual type into.
	 */
	private static function assert_optional_string( $argument, $message = 'Argument must be of type string or null, %s provided.' ) {
		if ( null !== $argument ) {
			self::assert_string( $argument, $message );
		}
	}

	/**
	 * Assert that the given argument is of type string and not empty.
	 *
	 * @param mixed  $argument Argument to assert the type of.
	 * @param string $message  Optional. The message to use for the exception.
	 *                         The message string should contain a placeholder
	 *                         for sprintf to insert the actual type into.
	 */
	private static function assert_nonempty_string( $argument, $message = 'Argument must be of type string and not empty, %s provided.' ) {
		self::assert_string( $argument, $message );
		if ( empty( $argument ) ) {
			throw new InvalidArgumentException(
				sprintf(
					$message,
					"\"{$argument}\""
				)
			);
		}
	}

	/**
	 * Assert that all elements of a given array argument are of type string.
	 *
	 * @param array  $argument Array argument to assert the type of the elements
	 *                         of.
	 * @param string $message  Optional. The message to use for the exception.
	 *                         The message string should contain a placeholder
	 *                         for sprintf to insert the actual type into.
	 */
	private static function assert_all_strings( $argument, $message = 'Argument must be of type string or null, %s provided.' ) {
		foreach ( $argument as $element ) {
			self::assert_string( $element, $message );
		}
	}
}
