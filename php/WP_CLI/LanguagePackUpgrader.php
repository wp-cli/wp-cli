<?php

namespace WP_CLI;

use WP_CLI;

/**
 * A Language Pack Upgrader class that caches the download, and uses cached if available
 *
 * @package wp-cli
 */
class LanguagePackUpgrader extends \Language_Pack_Upgrader {

	/**
	 * Caches the download, and uses cached if available.
	 *
	 * @access public
	 *
	 * @param string $package The URI of the package. If this is the full path to an
	 *                        existing local file, it will be returned untouched.
	 * @return string|WP_Error The full path to the downloaded package file, or a WP_Error object.
	 */
	public function download_package( $package ) {

		/**
		 * Filter whether to return the package.
		 *
		 * @since 3.7.0
		 *
		 * @param bool    $reply   Whether to bail without returning the package. Default is false.
		 * @param string  $package The package file name.
		 * @param object  $this    The WP_Upgrader instance.
		 */
		$reply = apply_filters( 'upgrader_pre_download', false, $package, $this );
		if ( false !== $reply ) {
			return $reply;
		}

		// Check if package is a local or remote file. Bail if it's local.
		if ( ! preg_match( '!^(http|https|ftp)://!i', $package ) && file_exists( $package ) ) {
			return $package;
		}

		if ( empty( $package ) ) {
			return new \WP_Error( 'no_package', $this->strings['no_package'] );
		}

		$language_update = $this->skin->language_update;
		$type            = $language_update->type;
		$slug            = empty( $language_update->slug ) ? 'default' : $language_update->slug;
		$updated         = strtotime( $language_update->updated );
		$version         = $language_update->version;
		$language        = $language_update->language;
		$ext             = pathinfo( $package, PATHINFO_EXTENSION );

		$temp = \WP_CLI\Utils\get_temp_dir() . uniqid( 'wp_' ) . '.' . $ext;

		$cache = WP_CLI::get_cache();
		$cache_key = "translation/{$type}-{$slug}-{$version}-{$language}-{$updated}.{$ext}";
		$cache_file = $cache->has( $cache_key );

		if ( $cache_file ) {
			WP_CLI::log( "Using cached file '$cache_file'..." );
			copy( $cache_file, $temp );
			return $temp;
		} else {
			/*
			 * Download to a temporary file because piping from cURL to tar is flaky
			 * on MinGW (and probably in other environments too).
			 */
			$headers = array( 'Accept' => 'application/json' );
			$options = array(
				'timeout'  => 600,  // 10 minutes ought to be enough for everybody.
				'filename' => $temp
			);

			$this->skin->feedback( 'downloading_package', $package );

			/** @var \Requests_Response|null $req */
			$req = Utils\http_request( 'GET', $package, null, $headers, $options );
			if ( ! is_null( $req ) && $req->status_code !== 200 ) {
				return new \WP_Error( 'download_failed', $this->strings['download_failed'] );
			}
			$cache->import( $cache_key, $temp );
			return $temp;
		}
	}
}
