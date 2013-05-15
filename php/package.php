<?php

namespace WP_CLI\Package;

use \WP_CLI;
use WP_CLI\Utils;

/**
 * Get details about a package
 *
 * @param string            $package_slug       Slug for the package
 * @return array|WP_Error   $package_details    Details when the package exists, WP_Error if a problem
 */
function get_details( $package_slug ) {

	$package_directory_details = get_directory_details();

	if ( isset( $package_directory_details[$package_slug] ) )
		return $package_directory_details[$package_slug];
	else
		return new \WP_Error( 'missing-package', "Package not available in the directory." );
}

/**
 * Install a package
 *
 * @param string            $package_slug       Slug for the package
 * @return bool|WP_Error    $package_slug       True on success, WP_Error on failure
 */
function install( $package_slug, $version = 'dev-master' ) {

	if ( is_installed( $package_slug ) )
		return new \WP_Error( 'package-installed', "Package is already installed." );

	$output = do_composer( 'require', $package_slug . ':' . $version );

	// @todo validate the installation happened successfully

	return true; 
}

/**
 * Uninstall a package
 *
 * @param string            $package_slug       Slug for the package
 * @return bool|WP_Error    $package_slug       True on success, WP_Error on failure
 */
function uninstall( $package_slug ) {

	$package_details = get_details( $package_slug );
	if ( \is_wp_error( $package_details ) )
		return $package_details;

	if ( ! is_installed( $package_slug ) )
		return new \WP_Error( 'package-missing', "Package isn't installed." );

	// Delete the directory
	$cmd = 'cd ' . \WP_CLI_ROOT . 'packages/vendor/;rm -rf ' . escapeshellarg( $package_slug );
	exec( $cmd, $output );

	// Modify the composer.json... there must be a better way
	$composer_json = json_decode( file_get_contents( \WP_CLI_ROOT . 'packages/composer.json' ) );
	unset( $composer_json->require->$package_slug );
	file_put_contents( \WP_CLI_ROOT . 'packages/composer.json' , json_encode( $composer_json ) );

	// @todo maybe delete composer.lock
	return true;
}

/**
 * Whether a given package is installed or not
 */
function is_installed( $package_slug ) {

	$output = do_composer( 'show', '--installed' );
	if ( \is_wp_error( $output ) )
		return $output;

	foreach( (array)$output as $line ) {
		$line_pieces = explode( ' ', $line );
		if ( $package_slug == $line_pieces[0] )
			return true;
	}
	return false;
}

/**
 * Perform a Composer action
 *
 * @param string       $action      Action to perform (e.g. 'require')
 */
function do_composer( $action, $args = '' ) {

	$cmd = 'cd ' . \WP_CLI_ROOT . 'packages/; composer ' . escapeshellarg( $action ) . ' ' . escapeshellarg( $args );
	exec( $cmd, $output );
	return $output;
}

/**
 * Get details about all of the packages
 *
 * @return array      $directory_details      Details on each package
 */
function get_directory_details() {

	static $directory_details;
	if ( ! empty( $directory_details ) )
		return $directory_details;

	$directory = do_composer( 'search', 'wp-cli' );
	$directory_details = array();
	foreach( $directory as $package_line ) {
		$package_line = explode( ' ', $package_line );

		$package = new \stdClass;
		$package->slug = array_shift( $package_line );
		$package->installed = is_installed( $package->slug );
		$package->description = implode( ' ', $package_line );

		$directory_details[$package->slug] = $package;
	}

	return $directory_details;
}
