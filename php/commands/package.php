<?php
/**
 * Install and use community packages.
 *
 * @package wp-cli
 */
class Package_Command extends WP_CLI_Command {

	private $fields = array(
				'slug',
				'installed',
				'description'
			); 

	/**
	 * List all available community packages.
	 *
	 * @subcommand list [--fields=<fields>] [--format=<format>]
	 */
	public function _list( $args, $assoc_args ) {

		$defaults = array(
				'fields'          => $this->fields,
				'format'          => 'table',
			);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$package_directory = \WP_CLI\Package\get_directory_details();

		if ( is_wp_error( $package_directory ) )
			WP_CLI::error( $package_directory->get_error_message() );

		foreach( $package_directory as &$package ) {
			$package->installed = ( $package->installed ) ? 'yes' : 'no';
		}

		\WP_CLI\Utils\format_items( $assoc_args['format'], $assoc_args['fields'], $package_directory );
	}

	/**
	 * Install a new community package.
	 *
	 * @subcommand install
	 * @synopsis <package-slug>
	 */
	public function install( $args ) {

		list( $package_slug ) = $args;

		$ret = \WP_CLI\Package\install( $package_slug );
		if ( is_wp_error( $ret ) )
			WP_CLI::error( $ret->get_error_message() );
		else
			WP_CLI::success( sprintf( "Package '%s' installed.", $package_slug ) );
	}

	/**
	 * Uninstall an existing community package.
	 *
	 * @subcommand uninstall
	 * @synopsis <package-slug>
	 */
	public function uninstall( $args, $assoc_args ) {

		list( $package_slug ) = $args;

		$ret = \WP_CLI\Package\uninstall( $package_slug );
		if ( is_wp_error( $ret ) )
			WP_CLI::error( $ret->get_error_message() );
		else
			WP_CLI::success( sprintf( "Package '%s' uninstalled.", $package_slug ) );
	}

}

WP_CLI::add_command( 'package', 'Package_Command' );