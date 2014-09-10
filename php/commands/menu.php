<?php

/**
 * List, create, assign, and delete menus
 *
 * ## EXAMPLES
 *
 *     # Create a new menu
 *     wp menu create "My Menu"
 *
 *     # List existing menus
 *     wp menu list
 *
 *     # Create a new menu link item
 *     wp menu item add-custom sidebar-menu Apple http://apple.com --porcelain
 *
 *     # Assign the 'primary-menu' menu to the 'primary' location
 *     wp menu location assign primary-menu primary
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

		$menu_id = wp_create_nav_menu( $args[0] );

		if ( is_wp_error( $menu_id ) ) {

			WP_CLI::error( $menu_id->get_error_message() );

		} else {

			if ( isset( $assoc_args['porcelain'] ) ) {
				WP_CLI::line( $menu_id );
			} else {
				WP_CLI::success( "Created menu $menu_id." );
			}

		}
	}

	/**
	 * Delete one or more menus
	 *
	 * <menu>...
	 * : The name, slug, or term ID for the menu(s)
	 *
	 * ## EXAMPLES
	 *
	 *     wp menu delete "My Menu"
	 */
	public function delete( $args, $_ ) {

		foreach( $args as $arg ) {

			$ret = wp_delete_nav_menu( $arg );

			if ( ! $ret || is_wp_error( $ret ) ) {

				WP_CLI::warning( "Error deleting menu." );

			}

		}

		WP_CLI::success( "Menu(s) deleted." );

	}

	/**
	 * Get a list of menus.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count, ids. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each menu:
	 *
	 * * term_id
	 * * name
	 * * slug
	 * * count
	 *
	 * These fields are optionally available:
	 *
	 * * term_group
	 * * term_taxonomy_id
	 * * taxonomy
	 * * description
	 * * parent
	 * * locations
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
 *
 * ## EXAMPLES
 *
 *     # Add an existing post to an existing menu
 *     wp menu item add-post sidebar-menu 33 --title="Custom Test Post"
 *
 *     # Create a new menu link item
 *     wp menu item add-custom sidebar-menu Apple http://apple.com --porcelain
 */
class Menu_Item_Command extends WP_CLI_Command {

	protected $obj_fields = array(
		'db_id',
		'type',
		'title',
		'link',
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
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count, ids. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each menu item:
	 *
	 * * db_id
	 * * type
	 * * title
	 * * link
	 * * position
	 *
	 * These fields are optionally available:
	 *
	 * * menu_item_parent
	 * * object_id
	 * * object
	 * * type
	 * * type_label
	 * * target
	 * * attr_title
	 * * description
	 * * classes
	 * * xfn
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

		// Correct position inconsistency and
		// protected `url` param in WP-CLI
		$items = array_map( function( $item ) use ( $assoc_args ) {
			$item->position = $item->menu_order;
			$item->link = $item->url;
			if ( empty( $assoc_args['format'] ) || in_array( $assoc_args['format'], array( 'csv', 'json' ) ) ) {
				$item->classes = json_encode( $item->classes );
			}
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
	 * [--link=<link>]
	 * : Set a custom url for the menu item
	 *
	 * [--description=<description>]
	 * : Set a custom description for the menu item
	 *
	 * [--attr-title=<attr-title>]
	 * : Set a custom title attribute for the menu item
	 *
	 * [--target=<target>]
	 * : Set a custom link target for the menu item
	 *
	 * [--classes=<classes>]
	 * : Set a custom link classes for the menu item
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
	 * ## EXAMPLES
	 *
	 *     wp menu item add-post sidebar-menu 33 --title="Custom Test Post"
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

		$this->add_or_update_item( 'add', 'post_type', $args, $assoc_args );
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
	 * [--title=<title>]
	 * : Set a custom title for the menu item
	 *
	 * [--link=<link>]
	 * : Set a custom url for the menu item
	 *
	 * [--description=<description>]
	 * : Set a custom description for the menu item
	 *
	 * [--attr-title=<attr-title>]
	 * : Set a custom title attribute for the menu item
	 *
	 * [--target=<target>]
	 * : Set a custom link target for the menu item
	 *
	 * [--classes=<classes>]
	 * : Set a custom link classes for the menu item
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
	 * ## EXAMPLES
	 *
	 *     wp menu item add-term sidebar-menu post_tag 24
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

		$this->add_or_update_item( 'add', 'taxonomy', $args, $assoc_args );
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
	 * <link>
	 * : Target URL for the link
	 *
	 * [--description=<description>]
	 * : Set a custom description for the menu item
	 *
	 * [--attr-title=<attr-title>]
	 * : Set a custom title attribute for the menu item
	 *
	 * [--target=<target>]
	 * : Set a custom link target for the menu item
	 *
	 * [--classes=<classes>]
	 * : Set a custom link classes for the menu item
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
	 * ## EXAMPLES
	 *
	 *     wp menu item add-custom sidebar-menu Apple http://apple.com --porcelain
	 *
	 * @subcommand add-custom
	 */
	public function add_custom( $args, $assoc_args ) {

		$assoc_args['title'] = $args[1];
		unset( $args[1] );
		$assoc_args['link'] = $args[2];
		unset( $args[2] );
		$this->add_or_update_item( 'add', 'custom', $args, $assoc_args );
	}

	/**
	 * Update a menu item
	 *
	 * <db-id>
	 * : Database ID for the menu item.
	 *
	 * [--title=<title>]
	 * : Set a custom title for the menu item
	 *
	 * [--link=<link>]
	 * : Set a custom url for the menu item
	 *
	 * [--description=<description>]
	 * : Set a custom description for the menu item
	 *
	 * [--attr-title=<attr-title>]
	 * : Set a custom title attribute for the menu item
	 *
	 * [--target=<target>]
	 * : Set a custom link target for the menu item
	 *
	 * [--classes=<classes>]
	 * : Set a custom link classes for the menu item
	 *
	 * [--position=<position>]
	 * : Specify the position of this menu item.
	 *
	 * [--parent-id=<parent-id>]
	 * : Make this menu item a child of another menu item
	 *
	 * ## EXAMPLES
	 *
	 *     wp menu item update 45 --title=WordPress --link='http://wordpress.org' --target=_blank --position=2
	 *
	 * @subcommand update
	 */
	public function update( $args, $assoc_args ) {

		// Shuffle the position of these
		$args[1] = $args[0];
		$terms = get_the_terms( $args[1], 'nav_menu' );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$args[0] = (int)$terms[0]->term_id;
		} else {
			$args[0] = 0;
		}
		$type = get_post_meta( $args[1], '_menu_item_type', true );
		$this->add_or_update_item( 'update', $type, $args, $assoc_args );

	}

	/**
	 * Delete one or more items from a menu
	 *
	 * <db-id>...
	 * : Database ID for the menu item(s).
	 *
	 * ## EXAMPLES
	 *
	 *     wp menu item remove 45
	 *
	 * @subcommand delete
	 */
	public function delete( $args, $_ ) {

		foreach( $args as $arg ) {

			$ret = wp_delete_post( $arg, true );
			if ( ! $ret ) {
				WP_CLI::warning( "Couldn't delete menu item." );
			}

		}
		WP_CLI::success( "Menu item(s) deleted." );

	}

	/**
	 * Worker method to create new items or update existing ones
	 */
	private function add_or_update_item( $method, $type, $args, $assoc_args ) {

		$menu = $args[0];
		$menu_item_db_id = ( isset( $args[1] ) ) ? $args[1] : 0;

		$menu = wp_get_nav_menu_object( $menu );
		if ( ! $menu || is_wp_error( $menu ) ) {
			WP_CLI::error( "Invalid menu." );
		}

		// `url` is protected in WP-CLI, so we use `link` instead
		if ( isset( $assoc_args['link'] ) ) {
			$assoc_args['url'] = $assoc_args['link'];
		}

		// Need to persist the menu item data. See https://core.trac.wordpress.org/ticket/28138
		if ( 'update' == $method ) {

			$menu_item_obj = get_post( $menu_item_db_id );
			$menu_item_obj = wp_setup_nav_menu_item( $menu_item_obj );

			// Correct the menu position if this was the first item. See https://core.trac.wordpress.org/ticket/28140
			$position = ( 0 === $menu_item_obj->menu_order ) ? 1 : $menu_item_obj->menu_order;

			$default_args = array(
				'position'     => $position,
				'title'        => $menu_item_obj->title,
				'url'          => $menu_item_obj->url,
				'description'  => $menu_item_obj->description,
				'object'       => $menu_item_obj->object,
				'object-id'    => $menu_item_obj->object_id,
				'parent-id'    => $menu_item_obj->menu_item_parent,
				'attr-title'   => $menu_item_obj->attr_title,
				'target'       => $menu_item_obj->target,
				'classes'      => implode( ' ', $menu_item_obj->classes ), // stored in the database as array
				'xfn'          => $menu_item_obj->xfn,
				'status'       => $menu_item_obj->post_status,
				);

		} else {

			$default_args = array(
				'position'     => 0,
				'title'        => '',
				'url'          => '',
				'description'  => '',
				'object'       => '',
				'object-id'    => 0,
				'parent-id'    => 0,
				'attr-title'   => '',
				'target'       => '',
				'classes'      => '',
				'xfn'          => '',
				// Core oddly defaults to 'draft' for create,
				// and 'publish' for update
				// Easiest to always work with publish
				'status'       => 'publish',
				);

		}

		$menu_item_args = array();
		foreach( $default_args as $key => $default_value ) {
			// wp_update_nav_menu_item() has a weird argument prefix
			$new_key = 'menu-item-' . $key;
			$menu_item_args[ $new_key ] = isset( $assoc_args[ $key ] ) ? $assoc_args[ $key ] : $default_value;
		}

		$menu_item_args['menu-item-type'] = $type;
		$ret = wp_update_nav_menu_item( $menu->term_id, $menu_item_db_id, $menu_item_args );

		if ( is_wp_error( $ret ) ) {
			WP_CLI::error( $ret->get_error_message() );
		} else if ( ! $ret ) {
			if ( 'add' == $method ) {
				WP_CLI::error( "Couldn't add menu item." );
			} else if ( 'update' == $method ) {
				WP_CLI::error( "Couldn't update menu item." );
			}
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

			if ( 'add' == $method && ! empty( $assoc_args['porcelain'] ) ) {
				WP_CLI::line( $ret );
			} else {
				if ( 'add' == $method ) {
					WP_CLI::success( "Menu item added." );
				} else if ( 'update' == $method ) {
					WP_CLI::success( "Menu item updated." );
				}
			}
		}

	}

	protected function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields );
	}

}

/**
 * Manage a menu's assignment to locations.
 *
 * ## EXAMPLES
 *
 *     # List available menu locations
 *     wp menu location list
 *
 *     # Assign the 'primary-menu' menu to the 'primary' location
 *     wp menu location assign primary-menu primary
 *
 *     # Remove the 'primary-menu' menu from the 'primary' location
 *     wp menu location remove primary-menu primary
 */
class Menu_Location_Command extends WP_CLI_Command {

	/**
	 * List locations for the current theme.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count, ids. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each location:
	 *
	 * * name
	 * * description
	 *
	 * ## EXAMPLES
	 *
	 *     wp menu location list
	 *
	 * @subcommand list
	 */
	public function list_( $_, $assoc_args ) {

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
	 * ## EXAMPLES
	 *
	 *     wp menu location assign primary-menu primary
	 *
	 * @subcommand assign
	 */
	public function assign( $args, $_ ) {

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
	 * ## EXAMPLES
	 *
	 *     wp menu location remove primary-menu primary
	 *
	 * @subcommand remove
	 */
	public function remove( $args, $_ ) {

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


}

WP_CLI::add_command( 'menu', 'Menu_Command' );
WP_CLI::add_command( 'menu item', 'Menu_Item_Command' );
WP_CLI::add_command( 'menu location', 'Menu_Location_Command' );
