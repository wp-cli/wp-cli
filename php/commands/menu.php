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
		// < 3.6 $menu_locations could be false
		if ( ! $menu_locations ) {
			$menu_locations = array();
		}
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
		'position',
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

		// Correct position inconsistency
		$items = array_map( function( $item ) {
			$item->position = $item->menu_order;
			return $item;
		}, $items );

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $items );

	}

	/**
	 * Add a post as a menu item
	 * 
	 * <menu>
	 * : The name, slug, or term ID for the menu
	 * 
	 * <post-id>
	 * : Post ID to add to the menu
	 * 
	 * [--title=<title>]
	 * : Set a custom title for the menu item
	 * 
	 * [--description=<description>]
	 * : Set a custom description for the menu item
	 * 
	 * [--position=<position>]
	 * : Specify the position of this menu item.
	 * 
	 * [--parent-id=<parent-id>]
	 * : Make this menu item a child of another menu item
	 * 
	 * [--porcelain]
	 * : Output just the new menu item id.
	 * 
	 * @subcommand add-post
	 */
	public function add_post( $args, $assoc_args ) {

		$assoc_args['object-id'] = $args[1];
		unset( $args[1] );
		$post = get_post( $assoc_args['object-id'] );
		if ( ! $post ) {
			WP_CLI::error( "Invalid post." );
		}
		$assoc_args['object'] = $post->post_type;

		$this->add_or_update_item( 'post_type', $args, $assoc_args );
	}

	/**
	 * Add a taxonomy term as a menu item
	 * 
	 * <menu>
	 * : The name, slug, or term ID for the menu
	 * 
	 * <taxonomy>
	 * : Taxonomy of the term to be added
	 * 
	 * <term-id>
	 * : Term ID of the term to be added
	 * 
	 * [--porcelain]
	 * : Output just the new menu item id.
	 * 
	 * @subcommand add-term
	 */
	public function add_term( $args, $assoc_args ) {

		$assoc_args['object'] = $args[1];
		unset( $args[1] );
		$assoc_args['object-id'] = $args[2];
		unset( $args[2] );

		if ( ! get_term_by( 'id', $assoc_args['object-id'], $assoc_args['object'] ) ) {
			WP_CLI::error( "Invalid term." );
		}

		$this->add_or_update_item( 'taxonomy', $args, $assoc_args );
	}

	/**
	 * Add a custom menu item
	 * 
	 * <menu>
	 * : The name, slug, or term ID for the menu
	 * 
	 * <title>
	 * : Title for the link
	 * 
	 * <url>
	 * : Target URL for the link
	 * 
	 * [--porcelain]
	 * : Output just the new menu item id.
	 * 
	 * @subcommand add-custom
	 */
	public function add_custom( $args, $assoc_args ) {

		$assoc_args['title'] = $args[1];
		unset( $args[1] );
		$assoc_args['url'] = $args[2];
		unset( $args[2] );
		$this->add_or_update_item( 'custom', $args, $assoc_args );
	}

	/**
	 * Remove an item from a menu
	 * 
	 * <db-id>
	 * : Database ID for the menu item.
	 * 
	 * @subcommand remove
	 */
	public function remove( $args, $_ ) {

		$ret = wp_delete_post( $args[0], true );
		if ( $ret ) {
			WP_CLI::success( "Menu item deleted." );
		} else {
			WP_CLI::error( "Couldn't delete menu item." );
		}

	}

	/**
	 * Worker method to create new items or update existing ones
	 */
	private function add_or_update_item( $type, $args, $assoc_args ) {

		$menu = $args[0];
		$menu_item_db_id = ( isset( $args[1] ) ) ? $args[1] : 0;

		$menu = wp_get_nav_menu_object( $menu );
		if ( ! $menu || is_wp_error( $menu ) ) {
			WP_CLI::error( "Invalid menu." );
		}

		$default_args = array(
			'position'     => 0,
			'title'        => '',
			'url'          => '',
			'description'  => '',
			'object'       => '',
			'object-id'    => '',
			'attr-title'   => '',
			'target'       => '',
			'classes'      => '',
			'xfn'          => '',
			'status'       => '',
			);
		$menu_item_args = array();
		foreach( $default_args as $key => $default_value ) {
			// wp_update_nav_menu_item() has a weird argument prefix 
			$new_key = 'menu-item-' . $key;
			if ( isset( $assoc_args[ $key ] ) ) {
				$menu_item_args[ $new_key ] = $assoc_args[ $key ];
			}
		}

		// Core oddly defaults to 'draft' for create,
		// and 'publish' for update
		// Easiest to always work with publish
		if ( ! isset( $menu_item_args['menu-item-status'] ) ) {
			$menu_item_args['menu-item-status'] = 'publish';
		}

		$menu_item_args['menu-item-type'] = $type;
		$ret = wp_update_nav_menu_item( $menu->term_id, $menu_item_db_id, $menu_item_args );

		if ( is_wp_error( $ret ) ) {
			WP_CLI::error( $ret->get_error_message() );
		} else if ( ! $ret ) {
			WP_CLI::error( "Couldn't add menu item." );
		} else {

			/**
			 * Set the menu
			 * 
			 * wp_update_nav_menu_item() *should* take care of this, but
			 * depends on wp_insert_post()'s "tax_input" argument, which
			 * is ignored if the user can't edit the taxonomy
			 * 
			 * @see https://core.trac.wordpress.org/ticket/27113
			 */
			if ( ! is_object_in_term( $ret, 'nav_menu', (int) $menu->term_id ) ) {
				wp_set_object_terms( $ret, array( (int)$menu->term_id ), 'nav_menu' );
			}

			if ( ! empty( $assoc_args['porcelain'] ) ) {
				WP_CLI::line( $ret );
			} else {
				WP_CLI::success( "Menu item added." );
			}
		}

	}

	protected function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields );
	}

}

WP_CLI::add_command( 'menu', 'Menu_Command' );
WP_CLI::add_command( 'menu item', 'Menu_Item_Command' );
