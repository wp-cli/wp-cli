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
	 * List locations for the current theme.
	 * 
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count, ids. Default: table
	 * 
	 * @subcommand theme-locations
	 */
	public function theme_locations( $_, $assoc_args ) {

		$locations = get_registered_nav_menus();
		$location_objs = array();
		foreach( $locations as $location => $description ) {
			$location_obj = new \stdClass;
			$location_obj->location = $location;
			$location_obj->description = $description;
			$location_objs[] = $location_obj;
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, array( 'location', 'description' ) );
		$formatter->display_items( $location_objs );
	}

	/**
	 * Assign a location to a menu
	 * 
	 * <menu>
	 * : The name, slug, or term ID for the menu
	 * 
	 * <location>
	 * : Location's slug
	 *
	 * @subcommand assign-location
	 */
	public function assign_location( $args, $_ ) {

		list( $menu, $location ) = $args;

		$menu = wp_get_nav_menu_object( $menu );
		if ( ! $menu || is_wp_error( $menu ) ) {
			WP_CLI::error( "Invalid menu." );
		} 

		$locations = get_registered_nav_menus();
		if ( ! array_key_exists( $location, $locations ) ) {
			WP_CLI::error( "Invalid location." );
		}

		$locations = get_nav_menu_locations();
		$locations[ $location ] = $menu->term_id; 

		set_theme_mod( 'nav_menu_locations', $locations );

		WP_CLI::success( "Assigned location to menu." );
	}

	/**
	 * Remove a location from a menu
	 * 
	 * <menu>
	 * : The name, slug, or term ID for the menu
	 * 
	 * <location>
	 * : Location's slug
	 *
	 * @subcommand remove-location
	 */
	public function remove_location( $args, $_ ) {

		list( $menu, $location ) = $args;

		$menu = wp_get_nav_menu_object( $menu );
		if ( ! $menu || is_wp_error( $menu ) ) {
			WP_CLI::error( "Invalid menu." );
		} 

		$locations = get_nav_menu_locations();
		if ( ! isset( $locations[ $location ] ) || $locations[ $location ] != $menu->term_id ) {
			WP_CLI::error( "Menu isn't assigned to location." );
		}

		$locations[ $location ] = 0; 
		set_theme_mod( 'nav_menu_locations', $locations );

		WP_CLI::success( "Removed location from menu." );

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

		if ( ! $ret || is_wp_error( $ret ) ) {

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

/**
 * List, add, and delete items associated with a menu
 */
class Menu_Item_Command extends WP_CLI_Command {

	protected $obj_fields = array(
		'db_id',
		'type',
		'title',
		'url',
	);

	/**
	 * Get a list of items associated with a menu
	 *
	 * ## OPTIONS
	 * 
	 * <menu>
	 * : The name, slug, or term ID for the menu
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields. Defaults to db_id,type,title,url
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count, ids. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp menu item list <menu>
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		$items = wp_get_nav_menu_items( $args[0] );
		if ( false === $items || is_wp_error( $items ) ) {
			WP_CLI::error( "Invalid menu" );
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $items );

	}

	protected function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields );
	}

}

WP_CLI::add_command( 'menu', 'Menu_Command' );
WP_CLI::add_command( 'menu item', 'Menu_Item_Command' );
