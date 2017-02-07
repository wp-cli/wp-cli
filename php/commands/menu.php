<?php

use WP_CLI\Utils;

/**
 * List, create, assign, and delete menus.
 *
 * ## EXAMPLES
 *
 *     # Create a new menu
 *     $ wp menu create "My Menu"
 *     Success: Created menu 200.
 *
 *     # List existing menus
 *     $ wp menu list
 *     +---------+----------+----------+-----------+-------+
 *     | term_id | name     | slug     | locations | count |
 *     +---------+----------+----------+-----------+-------+
 *     | 200     | My Menu  | my-menu  |           | 0     |
 *     | 177     | Top Menu | top-menu | primary   | 7     |
 *     +---------+----------+----------+-----------+-------+
 *
 *     # Create a new menu link item
 *     $ wp menu item add-custom my-menu Apple http://apple.com --porcelain
 *     1922
 *
 *     # Assign the 'my-menu' menu to the 'primary' location
 *     $ wp menu location assign my-menu primary
 *     Success: Assigned location to menu.
 *
 * @package wp-cli
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
	 * Create a new menu.
	 *
	 * ## OPTIONS
	 *
	 * <menu-name>
	 * : A descriptive name for the menu.
	 *
	 * [--porcelain]
	 * : Output just the new menu id.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp menu create "My Menu"
	 *     Success: Created menu 200.
	 */
	public function create( $args, $assoc_args ) {

		$menu_id = wp_create_nav_menu( $args[0] );

		if ( is_wp_error( $menu_id ) ) {

			WP_CLI::error( $menu_id->get_error_message() );

		} else {

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain' ) ) {
				WP_CLI::line( $menu_id );
			} else {
				WP_CLI::success( "Created menu $menu_id." );
			}

		}
	}

	/**
	 * Delete one or more menus.
	 *
	 * ## OPTIONS
	 *
	 * <menu>...
	 * : The name, slug, or term ID for the menu(s).
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp menu delete "My Menu"
	 *     Success: 1 menu deleted.
	 */
	public function delete( $args, $_ ) {

		$count = $errors = 0;
		foreach( $args as $arg ) {
			$ret = wp_delete_nav_menu( $arg );
			if ( ! $ret || is_wp_error( $ret ) ) {
				WP_CLI::warning( "Couldn't delete menu '{$arg}'." );
				$errors++;
			} else {
				WP_CLI::log( "Deleted menu '{$arg}'." );
				$count++;
			}
		}

		Utils\report_batch_operation_results( 'menu', 'delete', count( $args ), $count, $errors );
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
	 *     $ wp menu list
	 *     +---------+----------+----------+-----------+-------+
	 *     | term_id | name     | slug     | locations | count |
	 *     +---------+----------+----------+-----------+-------+
	 *     | 200     | My Menu  | my-menu  |           | 0     |
	 *     | 177     | Top Menu | top-menu | primary   | 7     |
	 *     +---------+----------+----------+-----------+-------+
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

			// Normalize the data for some output formats.
			if ( ! isset( $assoc_args['format'] ) || in_array( $assoc_args['format'], array( 'csv', 'table' ) ) ) {
				$menu->locations = implode( ',', $menu->locations );
			}
		}

		$formatter = $this->get_formatter( $assoc_args );

		if ( 'ids' == $formatter->format ) {
			$ids = array_map(
				function($o) {
					return $o->term_id;
				}, $menus
			);
			$formatter->display_items( $ids );
		} else {
			$formatter->display_items( $menus );
		}
	}

	protected function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields, $this->obj_type );
	}

}

WP_CLI::add_command( 'menu', 'Menu_Command' );
