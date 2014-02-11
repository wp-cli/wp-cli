<?php

/**
 * List, create, assign, and delete menus
 */
class Menu_Command extends WP_CLI_Command {

	protected $obj_type = 'nav_menu';
	protected $obj_fields = array(
		'term_id',
		'name',
		'slug',
		'locations',
		'count',
	);

	/**
	 * Create a new menu
	 * 
	 * <menu-name>
	 * : A descriptive name for the menu
	 *
	 * [--porcelain]
	 * : Output just the new menu id. 
	 * 
	 * ## EXAMPLES
	 *
	 *     wp menu create "My Menu"
	 */
	public function create( $args, $assoc_args ) {

		$ret = wp_create_nav_menu( $args[0] );

		if ( is_wp_error( $ret ) ) {

			WP_CLI::error( $ret->get_error_message() );

		} else {

			if ( isset( $assoc_args['porcelain'] ) ) {
				WP_CLI::line( $ret->term_id );
			} else {
				WP_CLI::success( "Created menu $ret->term_id." );
			}

		}
	}

	/**
	 * Delete a menu
	 * 
	 * <menu>
	 * : The name, slug, or term ID for the menu
	 * 
	 * ## EXAMPLES
	 *
	 *     wp menu delete "My Menu"
	 */
	public function delete( $args, $_ ) {

		$ret = wp_delete_nav_menu( $args[0] );

		if ( is_wp_error( $ret ) ) {

			WP_CLI::error( $ret->get_error_message() );

		} else if ( ! $ret ) {

			WP_CLI::error( "Error deleting menu." );
			
		} else {

			WP_CLI::success( "Menu deleted." );

		}
	}

	/**
	 * Get a list of menus.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields. Defaults to term_id,name,slug,count
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count, ids. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp menu list
	 *
	 * @subcommand list
	 */
	public function list_( $_, $assoc_args ) {

		$menus = wp_get_nav_menus();

		$menu_locations = get_nav_menu_locations();
		foreach( $menus as &$menu ) {

			$menu->locations = array();
			foreach( $menu_locations as $location => $term_id ) {

				if ( $term_id == $menu->term_id  ) {
					$menu->locations[] = $location;
				}

			}
			
			// Normalize the data for some output formats
			if ( ! isset( $assoc_args['format'] ) || in_array( $assoc_args['format'], array( 'csv', 'table' ) ) ) {
				$menu->locations = implode( ',', $menu->locations );
			}
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $menus );

	}

	protected function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields, $this->obj_type );
	}

}

WP_CLI::add_command( 'menu', 'Menu_Command' );
