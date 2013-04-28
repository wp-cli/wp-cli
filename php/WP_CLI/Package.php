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
function install( $package_slug ) {

	$package_details = get_details( $package_slug );
	if ( \is_wp_error( $package_details ) )
		return $package_details;

	if ( is_installed( $package_slug ) )
		return new \WP_Error( 'package-installed', "Package is already installed." );

	$package_local = WP_CLI::get_config( 'package_directory_local' ) . $package_slug . '/';
	return Utils\git_clone( $package_details->source, $package_local );
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

	$package_local = WP_CLI::get_config( 'package_directory_local' ) . $package_slug . '/';
	exec( 'rm -rf ' . $package_local, $results, $return );
	return true;
}

/**
 * Whether the package is installed or not
 */
function is_installed( $package_slug ) {
	// $all_commands = WP_CLI::$root->get_subcommands();
	// return (bool)isset( $all_commands[$package_slug] );
	return (bool)file_exists( WP_CLI::get_config( 'package_directory_local' ) . $package_slug . '/' );
}

/**
 * Get details about all of the packages
 *
 * @return array      $directory_details      Details on each package
 */
function get_directory_details() {

	if ( ! directory_exists() )
		return new \WP_Error( 'missing-directory', "Package Directory doesn't exist." );

	static $directory_details;
	if ( ! empty( $directory_details ) )
		return $directory_details;

	$directory_details = array();
	foreach( glob( WP_CLI::get_config( 'package_directory_local' ) . '*.yml' ) as $filename ) {
		
		$yml = spyc_load_file( $filename );

		if ( empty( $yml ) )
			continue;

		$package_details = new \stdClass;
		foreach( $yml as $key => $value ) {
			$key = strtolower( $key );
			$package_details->$key = $value;
		}

		$package_slug = str_replace( '.yml', '', basename( $filename ) );
		$package_details->slug = $package_slug;

		$package_details->installed = is_installed( $package_slug );

		$directory_details[$package_slug] = $package_details;
	}
	return $directory_details;
}

/**
 * Install the package directory.
 *
 * @return bool|WP_Error
 */
function install_directory() {

	if ( directory_exists() )
		return new \WP_Error( 'directory-exists', "Package directory already exists." );

	return Utils\git_clone( WP_CLI::get_config( 'package_directory_remote' ), WP_CLI::get_config( 'package_directory_local' ) );
}

/**
 * Update the package directory.
 *
 * @return bool|WP_Error
 */
function update_directory() {

	if ( ! directory_exists() )
		return new \WP_Error( 'directory-missing', "Package directory doesn't exist." );

	return Utils\git_pull( WP_CLI::get_config( 'package_directory_local' ) );
}

/**
 * Does the package directory exist locally?
 *
 * @return bool
 */
function directory_exists() {
	return (bool)file_exists( WP_CLI::get_config( 'package_directory_local' ) );
}
