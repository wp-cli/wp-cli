<?php

namespace WP_CLI;

use DirectoryIterator;
use Exception;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_CLI;
use ZipArchive;

/**
 * Extract a provided archive file.
 */
class Extractor {

	/**
	 * Extract the archive file to a specific destination.
	 *
	 * @param string $tarball_or_zip
	 * @param string $dest
	 * @return void
	 */
	public static function extract( $tarball_or_zip, $dest ) {
		if ( preg_match( '/\.zip$/', $tarball_or_zip ) ) {
			self::extract_zip( $tarball_or_zip, $dest );
			return;
		}

		if ( preg_match( '/\.tar\.gz$/', $tarball_or_zip ) ) {
			self::extract_tarball( $tarball_or_zip, $dest );
			return;
		}

		throw new Exception( "Extraction only supported for '.zip' and '.tar.gz' file types." );
	}

	/**
	 * Extract a ZIP file to a specific destination.
	 *
	 * @param string $zipfile
	 * @param string $dest
	 * @return void
	 */
	private static function extract_zip( $zipfile, $dest ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new Exception( 'Extracting a zip file requires ZipArchive.' );
		}

		// Ensure the destination folder exists or can be created.
		if ( ! self::ensure_dir_exists( $dest ) ) {
			throw new Exception( "Could not create folder '{$dest}'." );
		}

		if ( ! file_exists( $zipfile )
			|| ! is_readable( $zipfile )
			|| filesize( $zipfile ) <= 0 ) {
			throw new Exception( "Invalid zip file '{$zipfile}'." );
		}

		$zip = new ZipArchive();
		$res = $zip->open( $zipfile );

		if ( true === $res ) {
			$tempdir = Utils\make_temp_dir( 'wp-cli-extract-zipfile-' );

			$zip->extractTo( $tempdir );
			$zip->close();

			try {
				self::copy_overwrite_files(
					self::get_first_subfolder( $tempdir ),
					$dest
				);
			} finally {
				self::rmdir_quietly( $tempdir );
			}
		} else {
			throw new Exception(
				sprintf(
					"ZipArchive failed to unzip '%s': %s.",
					$zipfile,
					self::zip_error_msg( $res )
				)
			);
		}
	}

	/**
	 * Extract a tarball to a specific destination.
	 *
	 * @param string $tarball
	 * @param string $dest
	 * @return void
	 */
	private static function extract_tarball( $tarball, $dest ) {
		// Ensure the destination folder exists or can be created.
		if ( ! self::ensure_dir_exists( $dest ) ) {
			throw new Exception( "Could not create folder '{$dest}'." );
		}

		$tarball_absolute = realpath( $tarball );
		if ( ! $tarball_absolute ) {
			throw new Exception( "Invalid tarball '{$tarball}'." );
		}
		$tarball = $tarball_absolute;

		if ( ! is_readable( $tarball )
			|| filesize( $tarball ) <= 0 ) {
			throw new Exception( "Invalid tarball '{$tarball}'." );
		}

		$tar_error = null;
		$tempdir   = Utils\make_temp_dir( 'wp-cli-extract-tarball-' );

		try {
			// Extract into a temporary folder first, so that the archive
			// contents (e.g. symbolic links) can be validated before anything
			// is written to the destination.
			$force_local = Utils\is_windows() ? ' --force-local' : '';
			$cmd         = Utils\esc_cmd(
				"tar xz{$force_local} --strip-components=1 --directory=%s -f %s",
				Path::normalize( $tempdir ),
				Path::normalize( $tarball )
			);

			$process_run = WP_CLI::launch(
				$cmd,
				false, /*exit_on_error*/
				true /*return_detailed*/
			);

			if ( 0 !== $process_run->return_code ) {
				throw new Exception( (string) self::tar_error_msg( $process_run ) );
			}
		} catch ( Exception $e ) {
			$tar_error = $e->getMessage();
			if ( class_exists( 'PharData' ) ) {
				WP_CLI::warning(
					'tar xz failed, falling back to PharData ('
					. $tar_error . ')'
				);
			}
		}

		if ( null === $tar_error ) {
			try {
				self::copy_overwrite_files( $tempdir, $dest );
				return;
			} finally {
				self::rmdir_quietly( $tempdir );
			}
		}

		self::rmdir_quietly( $tempdir );

		$phar_error = null;

		if ( class_exists( 'PharData' ) ) {
			$tempdir = Utils\make_temp_dir( 'wp-cli-extract-tarball-' );

			try {
				$phar = new PharData( $tarball );
				$phar->extractTo( $tempdir );
			} catch ( Exception $e ) {
				$phar_error = $e->getMessage();
			}

			if ( null === $phar_error ) {
				try {
					self::copy_overwrite_files(
						self::get_first_subfolder( $tempdir ),
						$dest
					);
					return;
				} finally {
					self::rmdir_quietly( $tempdir );
				}
			}

			self::rmdir_quietly( $tempdir );
		}

		$errors = [];
		if ( $tar_error ) {
			$errors[] = "tar xz failed: {$tar_error}";
		}
		if ( $phar_error ) {
			$errors[] = "PharData failed: {$phar_error}";
		}

		if ( empty( $errors ) ) {
			throw new Exception( 'Failed to extract the tarball.' );
		}

		throw new Exception( 'Failed to extract the tarball. ' . implode( ' ', $errors ) );
	}

	/**
	 * Copy files from source directory to destination directory. Source
	 * directory must exist.
	 *
	 * Symbolic links are never copied, and existing symbolic links in the
	 * destination are never written through: a symbolic link in the source
	 * aborts the copy before anything is written, an existing symbolic link to
	 * a file is replaced by the copied file, and an existing symbolic link to
	 * a directory is only accepted when it resolves to a location inside the
	 * destination directory.
	 *
	 * @param string $source
	 * @param string $dest
	 * @return void
	 */
	public static function copy_overwrite_files( $source, $dest ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$source,
				RecursiveDirectoryIterator::SKIP_DOTS
			),
			RecursiveIteratorIterator::SELF_FIRST
		);

		/**
		 * @var \SplFileInfo $item
		 */
		foreach ( $iterator as $item ) {
			if ( $item->isLink() ) {
				throw new Exception(
					"Refusing to extract symbolic link '" . $iterator->getSubPathname() . "'."
				);
			}
		}

		$error = 0;

		if ( ! is_dir( $dest ) ) {
			mkdir( $dest, 0755, true );
		}

		$dest_root = realpath( $dest );
		if ( false === $dest_root ) {
			throw new Exception( "Could not resolve destination folder '{$dest}'." );
		}

		/**
		 * @var \SplFileInfo $item
		 */
		foreach ( $iterator as $item ) {

			$dest_path = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname();

			if ( $item->isDir() ) {
				if ( is_link( $dest_path ) ) {
					$real_path = realpath( $dest_path );
					if ( false === $real_path || ! is_dir( $real_path ) || ! self::is_inside( $real_path, $dest_root ) ) {
						throw new Exception(
							"Refusing to write through symbolic link '" . $iterator->getSubPathname() . "' pointing outside of '{$dest}'."
						);
					}
				} elseif ( ! is_dir( $dest_path ) ) {
					mkdir( $dest_path, 0755 );
				}
				continue;
			}

			if ( is_link( $dest_path ) ) {
				// Replace the link itself rather than writing to its target.
				if ( ! self::unlink_link( $dest_path ) ) {
					$error = 1;
					WP_CLI::warning( "Unable to replace symbolic link '" . $iterator->getSubPathname() . "'." );
					continue;
				}
			}

			$real_parent = realpath( dirname( $dest_path ) );
			if ( false === $real_parent || ! self::is_inside( $real_parent, $dest_root ) ) {
				throw new Exception(
					"Refusing to write '" . $iterator->getSubPathname() . "' outside of '{$dest}'."
				);
			}

			if ( file_exists( $dest_path ) && is_writable( $dest_path ) ) {
				copy( $item, $dest_path );
			} elseif ( ! file_exists( $dest_path ) ) {
				copy( $item, $dest_path );
			} else {
				$error = 1;
				WP_CLI::warning( "Unable to copy '" . $iterator->getSubPathname() . "' to current directory." );
			}
		}

		if ( $error ) {
			throw new Exception( 'There was an error overwriting existing files.' );
		}
	}

	/**
	 * Check whether a canonicalized path is the given root or inside of it.
	 *
	 * @param string $path Canonicalized path to check.
	 * @param string $root Canonicalized root directory.
	 * @return bool
	 */
	private static function is_inside( $path, $root ) {
		$root = rtrim( $root, '/\\' );
		if ( $path === $root ) {
			return true;
		}
		return 0 === strpos( $path, $root . DIRECTORY_SEPARATOR );
	}

	/**
	 * Remove a symbolic link without touching its target.
	 *
	 * @param string $path
	 * @return bool
	 */
	private static function unlink_link( $path ) {
		// Directory links on Windows need rmdir().
		return @unlink( $path ) || ( Utils\is_windows() && @rmdir( $path ) ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Delete a temporary directory, ignoring errors so that they don't mask
	 * a primary exception.
	 *
	 * @param string $dir
	 * @return void
	 */
	private static function rmdir_quietly( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		try {
			self::rmdir( $dir );
		} catch ( Exception $e ) {
			unset( $e );
		}
	}

	/**
	 * Delete all files and directories recursively from directory. Directory
	 * must exist.
	 *
	 * @param string $dir
	 * @return void
	 */
	public static function rmdir( $dir ) {
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator(
				$dir,
				RecursiveDirectoryIterator::SKIP_DOTS
			),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		$base_dir = realpath( $dir );
		if ( false === $base_dir ) {
			return;
		}
		$base_dir = rtrim( $base_dir, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;

		/**
		 * @var \SplFileInfo $fileinfo
		 */
		foreach ( $files as $fileinfo ) {
			$path = $fileinfo->getPathname();

			// Remove symbolic links themselves; this never touches their target.
			if ( $fileinfo->isLink() ) {
				self::unlink_link( $path );
				continue;
			}

			$todo      = $fileinfo->isDir() ? 'rmdir' : 'unlink';
			$real_path = $fileinfo->getRealPath();

			if ( ! $real_path || 0 !== strpos( $real_path, $base_dir ) ) {
				WP_CLI::warning(
					"Temporary file or folder to be removed was found outside of temporary folder, aborting removal: '{$path}'"
				);
				continue;
			}

			$todo( $path );
		}
		rmdir( $dir );
	}

	/**
	 * Return formatted ZipArchive error message from error code.
	 *
	 * @param int $error_code
	 * @return string|int The error message corresponding to the specified
	 *                    code, if found; Other wise the same error code,
	 *                    unmodified.
	 */
	public static function zip_error_msg( $error_code ) {
		// From https://github.com/php/php-src/blob/php-5.3.0/ext/zip/php_zip.c#L2623-L2646.
		static $zip_err_msgs = [
			ZipArchive::ER_OK          => 'No error',
			ZipArchive::ER_MULTIDISK   => 'Multi-disk zip archives not supported',
			ZipArchive::ER_RENAME      => 'Renaming temporary file failed',
			ZipArchive::ER_CLOSE       => 'Closing zip archive failed',
			ZipArchive::ER_SEEK        => 'Seek error',
			ZipArchive::ER_READ        => 'Read error',
			ZipArchive::ER_WRITE       => 'Write error',
			ZipArchive::ER_CRC         => 'CRC error',
			ZipArchive::ER_ZIPCLOSED   => 'Containing zip archive was closed',
			ZipArchive::ER_NOENT       => 'No such file',
			ZipArchive::ER_EXISTS      => 'File already exists',
			ZipArchive::ER_OPEN        => 'Can\'t open file',
			ZipArchive::ER_TMPOPEN     => 'Failure to create temporary file',
			ZipArchive::ER_ZLIB        => 'Zlib error',
			ZipArchive::ER_MEMORY      => 'Malloc failure',
			ZipArchive::ER_CHANGED     => 'Entry has been changed',
			ZipArchive::ER_COMPNOTSUPP => 'Compression method not supported',
			ZipArchive::ER_EOF         => 'Premature EOF',
			ZipArchive::ER_INVAL       => 'Invalid argument',
			ZipArchive::ER_NOZIP       => 'Not a zip archive',
			ZipArchive::ER_INTERNAL    => 'Internal error',
			ZipArchive::ER_INCONS      => 'Zip archive inconsistent',
			ZipArchive::ER_REMOVE      => 'Can\'t remove file',
			ZipArchive::ER_DELETED     => 'Entry has been deleted',
		];

		if ( isset( $zip_err_msgs[ $error_code ] ) ) {
			return sprintf(
				'%s (%d)',
				$zip_err_msgs[ $error_code ],
				$error_code
			);
		}
		return $error_code;
	}

	/**
	 * Return formatted error message from ProcessRun of tar command.
	 *
	 * @param ProcessRun $process_run
	 * @return string|int The error message of the process, if available;
	 *                    otherwise the return code.
	 */
	public static function tar_error_msg( $process_run ) {
		$stderr = trim( $process_run->stderr );
		$nl_pos = strpos( $stderr, "\n" );
		if ( false !== $nl_pos ) {
			$stderr = trim( substr( $stderr, 0, $nl_pos ) );
		}
		if ( $stderr ) {
			return sprintf( '%s (%d)', $stderr, $process_run->return_code );
		}
		return $process_run->return_code;
	}

	/**
	 * Return the first subfolder within a given path.
	 *
	 * Falls back to the provided path if no subfolder was detected.
	 *
	 * @param string $path Path to find the first subfolder in.
	 * @return string First subfolder, or same as $path if none found.
	 */
	private static function get_first_subfolder( $path ) {
		$iterator = new DirectoryIterator( $path );
		$path     = rtrim( $path, '/\\' );

		foreach ( $iterator as $fileinfo ) {
			if ( $fileinfo->isDir() && ! $fileinfo->isDot() && ! $fileinfo->isLink() ) {
				return "{$path}/{$fileinfo->getFilename()}";
			}
		}

		return $path;
	}

	/**
	 * Ensure directory exists.
	 *
	 * @param string $dir Directory to ensure the existence of.
	 * @return bool Whether the existence could be asserted.
	 */
	private static function ensure_dir_exists( $dir ) {
		if ( ! is_dir( $dir ) ) {
			if ( ! @mkdir( $dir, 0755, true ) ) {
				$error = error_get_last();
				WP_CLI::warning(
					sprintf(
						"Failed to create directory '%s': %s.",
						$dir,
						$error ? $error['message'] : 'Unknown error'
					)
				);
				return false;
			}
		}

		return true;
	}
}
