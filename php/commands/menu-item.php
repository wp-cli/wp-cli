<?php

use WP_CLI\Utils;

/**
 * List, add, and delete items associated with a menu.
 *
 * ## EXAMPLES
 *
 *     # Add an existing post to an existing menu
 *     $ wp menu item add-post sidebar-menu 33 --title="Custom Test Post"
 *     Success: Menu item added.
 *
 *     # Create a new menu link item
 *     $ wp menu item add-custom sidebar-menu Apple http://apple.com
 *     Success: Menu item added.
 *
 *     # Delete menu item
 *     $ wp menu item delete 45
 *     Success: 1 menu item deleted.
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
	 * Get a list of items associated with a menu.
	 *
	 * ## OPTIONS
	 *
	 * <menu>
	 * : The name, slug, or term ID for the menu.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 *   - ids
	 *   - yaml
	 * ---
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
	 *     $ wp menu item list main-menu
	 *     +-------+-----------+-------------+---------------------------------+----------+
	 *     | db_id | type      | title       | link                            | position |
	 *     +-------+-----------+-------------+---------------------------------+----------+
	 *     | 5     | custom    | Home        | http://example.com              | 1        |
	 *     | 6     | post_type | Sample Page | http://example.com/sample-page/ | 2        |
	 *     +-------+-----------+-------------+---------------------------------+----------+
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		$items = wp_get_nav_menu_items( $args[0] );
		if ( false === $items || is_wp_error( $items ) ) {
			WP_CLI::error( "Invalid menu." );
		}

		// Correct position inconsistency and
		// protected `url` param in WP-CLI
		$items = array_map( function( $item ) use ( $assoc_args ) {
			$item->position = $item->menu_order;
			$item->link = $item->url;
			return $item;
		}, $items );

		if ( ! empty( $assoc_args['format'] ) && 'ids' == $assoc_args['format'] ) {
			$items = array_map( function( $item ) {
				return $item->db_id;
			}, $items );
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $items );

	}

	/**
	 * Add a post as a menu item.
	 *
	 * ## OPTIONS
	 *
	 * <menu>
	 * : The name, slug, or term ID for the menu.
	 *
	 * <post-id>
	 * : Post ID to add to the menu.
	 *
	 * [--title=<title>]
	 * : Set a custom title for the menu item.
	 *
	 * [--link=<link>]
	 * : Set a custom url for the menu item.
	 *
	 * [--description=<description>]
	 * : Set a custom description for the menu item.
	 *
	 * [--attr-title=<attr-title>]
	 * : Set a custom title attribute for the menu item.
	 *
	 * [--target=<target>]
	 * : Set a custom link target for the menu item.
	 *
	 * [--classes=<classes>]
	 * : Set a custom link classes for the menu item.
	 *
	 * [--position=<position>]
	 * : Specify the position of this menu item.
	 *
	 * [--parent-id=<parent-id>]
	 * : Make this menu item a child of another menu item.
	 *
	 * [--porcelain]
	 * : Output just the new menu item id.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp menu item add-post sidebar-menu 33 --title="Custom Test Post"
	 *     Success: Menu item added.
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
	 * Add a taxonomy term as a menu item.
	 *
	 * ## OPTIONS
	 *
	 * <menu>
	 * : The name, slug, or term ID for the menu.
	 *
	 * <taxonomy>
	 * : Taxonomy of the term to be added.
	 *
	 * <term-id>
	 * : Term ID of the term to be added.
	 *
	 * [--title=<title>]
	 * : Set a custom title for the menu item.
	 *
	 * [--link=<link>]
	 * : Set a custom url for the menu item.
	 *
	 * [--description=<description>]
	 * : Set a custom description for the menu item.
	 *
	 * [--attr-title=<attr-title>]
	 * : Set a custom title attribute for the menu item.
	 *
	 * [--target=<target>]
	 * : Set a custom link target for the menu item.
	 *
	 * [--classes=<classes>]
	 * : Set a custom link classes for the menu item.
	 *
	 * [--position=<position>]
	 * : Specify the position of this menu item.
	 *
	 * [--parent-id=<parent-id>]
	 * : Make this menu item a child of another menu item.
	 *
	 * [--porcelain]
	 * : Output just the new menu item id.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp menu item add-term sidebar-menu post_tag 24
	 *     Success: Menu item added.
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
	 * Add a custom menu item.
	 *
	 * ## OPTIONS
	 *
	 * <menu>
	 * : The name, slug, or term ID for the menu.
	 *
	 * <title>
	 * : Title for the link.
	 *
	 * <link>
	 * : Target URL for the link.
	 *
	 * [--description=<description>]
	 * : Set a custom description for the menu item.
	 *
	 * [--attr-title=<attr-title>]
	 * : Set a custom title attribute for the menu item.
	 *
	 * [--target=<target>]
	 * : Set a custom link target for the menu item.
	 *
	 * [--classes=<classes>]
	 * : Set a custom link classes for the menu item.
	 *
	 * [--position=<position>]
	 * : Specify the position of this menu item.
	 *
	 * [--parent-id=<parent-id>]
	 * : Make this menu item a child of another menu item.
	 *
	 * [--porcelain]
	 * : Output just the new menu item id.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp menu item add-custom sidebar-menu Apple http://apple.com
	 *     Success: Menu item added.
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
	 * Update a menu item.
	 *
	 * ## OPTIONS
	 *
	 * <db-id>
	 * : Database ID for the menu item.
	 *
	 * [--title=<title>]
	 * : Set a custom title for the menu item.
	 *
	 * [--link=<link>]
	 * : Set a custom url for the menu item.
	 *
	 * [--description=<description>]
	 * : Set a custom description for the menu item.
	 *
	 * [--attr-title=<attr-title>]
	 * : Set a custom title attribute for the menu item.
	 *
	 * [--target=<target>]
	 * : Set a custom link target for the menu item.
	 *
	 * [--classes=<classes>]
	 * : Set a custom link classes for the menu item.
	 *
	 * [--position=<position>]
	 * : Specify the position of this menu item.
	 *
	 * [--parent-id=<parent-id>]
	 * : Make this menu item a child of another menu item.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp menu item update 45 --title=WordPress --link='http://wordpress.org' --target=_blank --position=2
	 *     Success: Menu item updated.
	 *
	 * @subcommand update
	 */
	public function update( $args, $assoc_args ) {

		// Shuffle the position of these.
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
	 * Delete one or more items from a menu.
	 *
	 * ## OPTIONS
	 *
	 * <db-id>...
	 * : Database ID for the menu item(s).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp menu item delete 45
	 *     Success: 1 menu item deleted.
	 *
	 * @subcommand delete
	 */
	public function delete( $args, $_ ) {
		global $wpdb;

		$count = $errors = 0;

		foreach( $args as $arg ) {

			$parent_menu_id = (int) get_post_meta( $arg, '_menu_item_menu_item_parent', true );
			$ret = wp_delete_post( $arg, true );
			if ( ! $ret ) {
				WP_CLI::warning( "Couldn't delete menu item {$arg}." );
				$errors++;
			} else if ( $parent_menu_id ) {
				$children = $wpdb->get_results( $wpdb->prepare( "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_menu_item_menu_item_parent' AND meta_value=%s", (int) $arg ) );
				if ( $children ) {
					$children_query = $wpdb->prepare( "UPDATE $wpdb->postmeta SET meta_value = %d WHERE meta_key = '_menu_item_menu_item_parent' AND meta_value=%s", $parent_menu_id, (int) $arg );
					$wpdb->query( $children_query );
					foreach( $children as $child ) {
						clean_post_cache( $child );
					}
				}
			}

			if ( false != $ret ) {
				$count++;
			}

		}

		Utils\report_batch_operation_results( 'menu item', 'delete', count( $args ), $count, $errors );
	}

	/**
	 * Worker method to create new items or update existing ones.
	 */
	private function add_or_update_item( $method, $type, $args, $assoc_args ) {

		$menu = $args[0];
		$menu_item_db_id = \WP_CLI\Utils\get_flag_value( $args, 1, 0 );

		$menu = wp_get_nav_menu_object( $menu );
		if ( ! $menu || is_wp_error( $menu ) ) {
			WP_CLI::error( "Invalid menu." );
		}

		// `url` is protected in WP-CLI, so we use `link` instead
		$assoc_args['url'] = \WP_CLI\Utils\get_flag_value( $assoc_args, 'link' );

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
			$menu_item_args[ $new_key ] = \WP_CLI\Utils\get_flag_value( $assoc_args, $key, $default_value );
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

WP_CLI::add_command( 'menu item', 'Menu_Item_Command' );
