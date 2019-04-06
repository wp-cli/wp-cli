<?php

namespace WP_CLI;

use Exception;
use PharData;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use WP_CLI;
use WP_CLI\Utils;
use ZipArchive;

/**
 * Extract a provided archive file.
 */
class Extractor {

	/**
	 * Extract the archive file to a specific destination.
	 *
	 * @param string $dest
	 */
	public static function extract( $tarball_or_zip, $dest ) {
		if ( preg_match( '/\.zip$/', $tarball_or_zip ) ) {
			return self::extract_zip( $tarball_or_zip, $dest );
		}

		if ( preg_match( '/\.tar\.gz$/', $tarball_or_zip ) ) {
			return self::extract_tarball( $tarball_or_zip, $dest );
		}
		throw new \Exception( "Extraction only supported for '.zip' and '.tar.gz' file types." );
	}

	/**
	 * Extract a ZIP file to a specific destination.
	 *
	 * @param string $zipfile
	 * @param string $dest
	 */
	private static function extract_zip( $zipfile, $dest ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new \Exception( 'Extracting a zip file requires ZipArchive.' );
		}
		$zip = new ZipArchive();
		$res = $zip->open( $zipfile );
		if ( true === $res ) {
			$tempdir = implode(
				DIRECTORY_SEPARATOR,
				array(
					dirname( $zipfile ),
					Utils\basename( $zipfile, '.zip' ),
					$zip->getNameIndex( 0 ),
				)
			);

			$zip->extractTo( dirname( $tempdir ) );
			$zip->close();

			self::copy_overwrite_files( $tempdir, $dest );
			self::rmdir( dirname( $tempdir ) );
		} else {
			throw new \Exception( sprintf( "ZipArchive failed to unzip '%s': %s.", $zipfile, self::zip_error_msg( $res ) ) );
		}
	}

	/**
	 * Extract a tarball to a specific destination.
	 *
	 * @param string $tarball
	 * @param string $dest
	 */
	private static function extract_tarball( $tarball, $dest ) {

		if ( class_exists( 'PharData' ) ) {
			try {
				$phar    = new PharData( $tarball );
				$tempdir = implode(
					DIRECTORY_SEPARATOR,
					array(
						dirname( $tarball ),
						Utils\basename( $tarball, '.tar.gz' ),
						$phar->getFilename(),
					)
				);

				$phar->extractTo( dirname( $tempdir ), null, true );

				self::copy_overwrite_files( $tempdir, $dest );

				self::rmdir( dirname( $tempdir ) );
				return;
			} catch ( \Exception $e ) {
				WP_CLI::warning( "PharData failed, falling back to 'tar xz' (" . $e->getMessage() . ')' );
				// Fall through to trying `tar xz` below
			}
		}
		// Note: directory must exist for tar --directory to work.
		$cmd         = Utils\esc_cmd( 'tar xz --strip-components=1 --directory=%s -f %s', $dest, $tarball );
		$process_run = WP_CLI::launch( $cmd, false /*exit_on_error*/, true /*return_detailed*/ );
		if ( 0 !== $process_run->return_code ) {
			throw new \Exception( sprintf( 'Failed to execute `%s`: %s.', $cmd, self::tar_error_msg( $process_run ) ) );
		}
	}

	/**
	 * Copy files from source directory to destination directory. Source directory must exist.
	 *
	 * @param string $source
	 * @param string $dest
	 */
	public static function copy_overwrite_files( $source, $dest ) {
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $source, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::SELF_FIRST
		);

		$error = 0;

		if ( ! is_dir( $dest ) ) {
			mkdir( $dest, 0777, true );
		}

		foreach ( $iterator as $item ) {

			$dest_path = $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

			if ( $item->isDir() ) {
				if ( ! is_dir( $dest_path ) ) {
					mkdir( $dest_path );
				}
			} else {
				if ( file_exists( $dest_path ) && is_writable( $dest_path ) ) {
					copy( $item, $dest_path );
				} elseif ( ! file_exists( $dest_path ) ) {
					copy( $item, $dest_path );
				} else {
					$error = 1;
					WP_CLI::warning( "Unable to copy '" . $iterator->getSubPathName() . "' to current directory." );
				}
			}
		}

		if ( $error ) {
			throw new \Exception( 'There was an error overwriting existing files.' );
		}
	}

	/**
	 * Delete all files and directories recursively from directory. Directory must exist.
	 *
	 * @param string $dir
	 */
	public static function rmdir( $dir ) {
		$files = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, RecursiveDirectoryIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $files as $fileinfo ) {
			$todo = $fileinfo->isDir() ? 'rmdir' : 'unlink';
			$todo( $fileinfo->getRealPath() );
		}
		rmdir( $dir );
	}

	/**
	 * Return formatted ZipArchive error message from error code.
	 *
	 * @param int $error_code
	 * @return string|int The error message corresponding to the specified code, if found;
	 * Other wise the same error code, unmodified.
	 */
	public static function zip_error_msg( $error_code ) {
		// From https://github.com/php/php-src/blob/php-5.3.0/ext/zip/php_zip.c#L2623-L2646
		static $zip_err_msgs = array(
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
		);

		if ( isset( $zip_err_msgs[ $error_code ] ) ) {
			return sprintf( '%s (%d)', $zip_err_msgs[ $error_code ], $error_code );
		}
		return $error_code;
	}

	/**
	 * Return formatted error message from ProcessRun of tar command.
	 *
	 * @param Processrun $process_run
	 * @return string|int The error message of the process, if available;
	 * otherwise the return code.
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
}
