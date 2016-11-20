<?php

use WP_CLI\Utils;

/**
 * Manage sidebar widgets.
 *
 * ## EXAMPLES
 *
 *     # List widgets on a given sidebar
 *     $ wp widget list sidebar-1
 *     +----------+------------+----------+----------------------+
 *     | name     | id         | position | options              |
 *     +----------+------------+----------+----------------------+
 *     | meta     | meta-6     | 1        | {"title":"Meta"}     |
 *     | calendar | calendar-2 | 2        | {"title":"Calendar"} |
 *     +----------+------------+----------+----------------------+
 *
 *     # Add a calendar widget to the second position on the sidebar
 *     $ wp widget add calendar sidebar-1 2
 *     Success: Added widget to sidebar.
 *
 *     # Update option(s) associated with a given widget
 *     $ wp widget update calendar-1 --title="Calendar"
 *     Success: Widget updated.
 *
 *     # Delete one or more widgets entirely
 *     $ wp widget delete calendar-2 archive-1
 *     Success: 2 widgets removed from sidebar.
 */

class Widget_Command extends WP_CLI_Command {

	private $fields = array(
		'name',
		'id',
		'position',
		'options',
		);

	/**
	 * List widgets associated with a sidebar.
	 *
	 * ## OPTIONS
	 *
	 * <sidebar-id>
	 * : ID for the corresponding sidebar.
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
	 *   - ids
	 *   - json
	 *   - count
	 *   - yaml
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each widget:
	 *
	 * * name
	 * * id
	 * * position
	 * * options
	 *
	 * There are no optionally available fields.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp widget list sidebar-1 --fields=name,id --format=csv
	 *     name,id
	 *     meta,meta-5
	 *     search,search-3
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		list( $sidebar_id ) = $args;

		$this->validate_sidebar( $sidebar_id );

		$output_widgets = $this->get_sidebar_widgets( $sidebar_id );

		if ( ! empty( $assoc_args['format'] ) && 'ids' === $assoc_args['format'] ) {
			$output_widgets = wp_list_pluck( $output_widgets, 'id' );
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields );
		$formatter->display_items( $output_widgets );

	}

	/**
	 * Add a widget to a sidebar.
	 *
	 * Creates a new widget entry in the database, and associates it with the
	 * sidebar.
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Widget name.
	 *
	 * <sidebar-id>
	 * : ID for the corresponding sidebar.
	 *
	 * [<position>]
	 * : Widget's current position within the sidebar. Defaults to last
	 *
	 * [--<field>=<value>]
	 * : Widget option to add, with its new value
	 *
	 * ## EXAMPLES
	 *
	 *     # Add a new calendar widget to sidebar-1 with title "Calendar"
	 *     $ wp widget add calendar sidebar-1 2 --title="Calendar"
	 *     Success: Added widget to sidebar.
	 *
	 * @subcommand add
	 */
	public function add( $args, $assoc_args ) {

		list( $name, $sidebar_id ) = $args;
		$position = \WP_CLI\Utils\get_flag_value( $args, 2, 1 ) - 1;
		$this->validate_sidebar( $sidebar_id );

		if ( false == ( $widget = $this->get_widget_obj( $name ) ) ) {
			WP_CLI::error( "Invalid widget type." );
		}

		/**
		 * Adding a widget is as easy as:
		 * 1. Creating a new widget option
		 * 2. Adding the widget to the sidebar
		 * 3. Positioning appropriately
		 */
		$widget_options = $option_keys = $this->get_widget_options( $name );
		if ( ! isset( $widget_options['_multiwidget'] ) ) {
			$widget_options['_multiwidget'] = 1;
		}
		unset( $option_keys['_multiwidget'] );
		$option_keys = array_keys( $option_keys );
		$last_key = array_pop( $option_keys );
		$option_index = $last_key + 1;
		$widget_options[ $option_index ] = $this->sanitize_widget_options( $name, $assoc_args, array() );
		$this->update_widget_options( $name, $widget_options );

		$widget_id = $name . '-' . $option_index;
		$this->move_sidebar_widget( $widget_id, null, $sidebar_id, null, $position );

		WP_CLI::success( "Added widget to sidebar." );


	}

	/**
	 * Update options for an existing widget.
	 *
	 * ## OPTIONS
	 *
	 * <widget-id>
	 * : Unique ID for the widget
	 *
	 * [--<field>=<value>]
	 * : Field to update, with its new value
	 *
	 * ## EXAMPLES
	 *
	 *     # Change calendar-1 widget title to "Our Calendar"
	 *     $ wp widget update calendar-1 --title="Our Calendar"
	 *     Success: Widget updated.
	 *
	 * @subcommand update
	 */
	public function update( $args, $assoc_args ) {

		list( $widget_id ) = $args;
		if ( ! $this->validate_sidebar_widget( $widget_id ) ) {
			WP_CLI::error( "Widget doesn't exist." );
		}

		if ( empty( $assoc_args ) ) {
			WP_CLI::error( "No options specified to update." );
		}

		list( $name, $option_index ) = $this->get_widget_data( $widget_id );

		$widget_options = $this->get_widget_options( $name );
		$clean_options = $this->sanitize_widget_options( $name, $assoc_args, $widget_options[ $option_index ] );
		$widget_options[ $option_index ] = array_merge( (array)$widget_options[ $option_index ], $clean_options );
		$this->update_widget_options( $name, $widget_options );

		WP_CLI::success( "Widget updated." );

	}

	/**
	 * Move the position of a widget.
	 *
	 * Changes the order of a widget in its existing sidebar, or moves it to a
	 * new sidebar.
	 *
	 * ## OPTIONS
	 *
	 * <widget-id>
	 * : Unique ID for the widget
	 *
	 * [--position=<position>]
	 * : Assign the widget to a new position.
	 *
	 * [--sidebar-id=<sidebar-id>]
	 * : Assign the widget to a new sidebar
	 *
	 * ## EXAMPLES
	 *
	 *     # Change position of widget
	 *     $ wp widget move recent-comments-2 --position=2
	 *     Success: Widget moved.
	 *
	 *     # Move widget to Inactive Widgets
	 *     $ wp widget move recent-comments-2 --sidebar-id=wp_inactive_widgets
	 *     Success: Widget moved.
	 *
	 * @subcommand move
	 */
	public function move( $args, $assoc_args ) {

		list( $widget_id ) = $args;
		if ( ! $this->validate_sidebar_widget( $widget_id ) ) {
			WP_CLI::error( "Widget doesn't exist." );
		}

		if ( empty( $assoc_args['position'] ) && empty( $assoc_args['sidebar-id'] ) ) {
			WP_CLI::error( "A new position or new sidebar must be specified." );
		}

		list( $name, $option_index, $current_sidebar_id, $current_sidebar_index ) = $this->get_widget_data( $widget_id );

		$new_sidebar_id = ! empty( $assoc_args['sidebar-id'] ) ? $assoc_args['sidebar-id'] : $current_sidebar_id;
		$this->validate_sidebar( $new_sidebar_id );

		$new_sidebar_index = ! empty( $assoc_args['position'] ) ? $assoc_args['position'] - 1 : $current_sidebar_index;
		// Moving between sidebars adds to the top
		if ( $new_sidebar_id != $current_sidebar_id && $new_sidebar_index == $current_sidebar_index ) {
			// Human-readable positions are different than numerically indexed array
			$new_sidebar_index = 0;
		}

		$this->move_sidebar_widget( $widget_id, $current_sidebar_id, $new_sidebar_id, $current_sidebar_index, $new_sidebar_index );

		WP_CLI::success( "Widget moved." );

	}

	/**
	 * Deactivate one or more widgets from an active sidebar.
	 *
	 * Moves widgets to Inactive Widgets.
	 *
	 * ## OPTIONS
	 *
	 * <widget-id>...
	 * : Unique ID for the widget(s)
	 *
	 * ## EXAMPLES
	 *
	 *     # Deactivate the recent-comments-2 widget.
	 *     $ wp widget deactivate recent-comments-2
	 *     Success: 1 widget deactivated.
	 *
	 * @subcommand deactivate
	 */
	public function deactivate( $args, $assoc_args ) {

		$count = $errors = 0;

		foreach( $args as $widget_id ) {
			if ( ! $this->validate_sidebar_widget( $widget_id ) ) {
				WP_CLI::warning( "Widget '{$widget_id}' doesn't exist." );
				$errors++;
				continue;
			}

			list( $name, $option_index, $sidebar_id, $sidebar_index ) = $this->get_widget_data( $widget_id );
			if ( 'wp_inactive_widgets' == $sidebar_id ) {
				WP_CLI::warning( sprintf( "'%s' is already deactivated.", $widget_id ) );
				continue;
			}

			$this->move_sidebar_widget( $widget_id, $sidebar_id, 'wp_inactive_widgets', $sidebar_index, 0 );

			$count++;

		}

		Utils\report_batch_operation_results( 'widget', 'deactivate', count( $args ), $count, $errors );
	}

	/**
	 * Delete one or more widgets from a sidebar.
	 *
	 * ## OPTIONS
	 *
	 * <widget-id>...
	 * : Unique ID for the widget(s)
	 *
	 * ## EXAMPLES
	 *
	 *     # Delete the recent-comments-2 widget from its sidebar.
	 *     $ wp widget delete recent-comments-2
	 *     Success: Deleted 1 of 1 widgets.
	 *
	 * @subcommand delete
	 */
	public function delete( $args, $assoc_args ) {

		$count = $errors = 0;

		foreach( $args as $widget_id ) {
			if ( ! $this->validate_sidebar_widget( $widget_id ) ) {
				WP_CLI::warning( "Widget '{$widget_id}' doesn't exist." );
				$errors++;
				continue;
			}

			// Remove the widget's settings.
			list( $name, $option_index, $sidebar_id, $sidebar_index ) = $this->get_widget_data( $widget_id );
			$widget_options = $this->get_widget_options( $name );
			unset( $widget_options[ $option_index ] );
			$this->update_widget_options( $name, $widget_options );

			// Remove the widget from the sidebar.
			$all_widgets = $this->wp_get_sidebars_widgets();
			unset( $all_widgets[ $sidebar_id ][ $sidebar_index ] );
			$all_widgets[ $sidebar_id ] = array_values( $all_widgets[ $sidebar_id ] );
			update_option( 'sidebars_widgets', $all_widgets );

			$count++;
		}

		Utils\report_batch_operation_results( 'widget', 'delete', count( $args ), $count, $errors );
	}

	/**
	 * Reset sidebar.
	 *
	 * Removes all widgets from the sidebar and places them in Inactive Widgets.
	 *
	 * ## OPTIONS
	 *
	 * [<sidebar-id>...]
	 * : One or more sidebars to reset.
	 *
	 * [--all]
	 * : If set, all sidebars will be reset.
	 *
	 * ## EXAMPLES
	 *
	 *     # Reset a sidebar
	 *     $ wp widget reset sidebar-1
	 *     Success: Sidebar 'sidebar-1' reset.
	 *
	 *     # Reset multiple sidebars
	 *     $ wp widget reset sidebar-1 sidebar-2
	 *     Success: Sidebar 'sidebar-1' reset.
	 *     Success: Sidebar 'sidebar-2' reset.
	 *
	 *     # Reset all sidebars
	 *     $ wp widget reset --all
	 *     Success: Sidebar 'sidebar-1' reset.
	 *     Success: Sidebar 'sidebar-2' reset.
	 *     Success: Sidebar 'sidebar-3' reset.
	 */
	public function reset( $args, $assoc_args ) {

		global $wp_registered_sidebars;

		$all = \WP_CLI\Utils\get_flag_value( $assoc_args, 'all', false );

		// Bail if no arguments and no all flag.
		if ( ! $all && empty( $args ) ) {
			WP_CLI::error( 'Please specify one or more sidebars, or use --all.' );
		}

		// Fetch all sidebars if all flag is set.
		if ( $all ) {
			$args = array_keys( $wp_registered_sidebars );
		}

		// Sidebar ID wp_inactive_widgets is reserved by WP core for inactive widgets.
		if ( isset( $args['wp_inactive_widgets'] ) ) {
			unset( $args['wp_inactive_widgets'] );
		}

		// Check if no registered sidebar.
		if ( empty( $args ) ) {
			WP_CLI::error( 'No sidebar registered.' );
		}

		$count = $errors = 0;
		foreach ( $args as $sidebar_id ) {
			if ( ! array_key_exists( $sidebar_id, $wp_registered_sidebars ) ) {
				WP_CLI::warning( sprintf( 'Invalid sidebar: %s', $sidebar_id ) );
				$errors++;
				continue;
			}

			$widgets = $this->get_sidebar_widgets( $sidebar_id );
			if ( empty( $widgets ) ) {
				WP_CLI::warning( sprintf( "Sidebar '%s' is already empty.", $sidebar_id ) );
			}
			else {
				foreach ( $widgets as $widget ) {
					$widget_id = $widget->id;
					list( $name, $option_index, $new_sidebar_id, $sidebar_index ) = $this->get_widget_data( $widget_id );
					$this->move_sidebar_widget( $widget_id, $new_sidebar_id, 'wp_inactive_widgets', $sidebar_index, 0 );
				}
				WP_CLI::log( sprintf( "Sidebar '%s' reset.", $sidebar_id ) );
				$count++;
			}
		}

		Utils\report_batch_operation_results( 'sidebar', 'reset', count( $args ), $count, $errors );
	}

	/**
	 * Check whether a sidebar is a valid sidebar
	 *
	 * @param string $sidebar_id
	 */
	private function validate_sidebar( $sidebar_id ) {
		global $wp_registered_sidebars;

		\WP_CLI\Utils\wp_register_unused_sidebar();

		if ( ! array_key_exists( $sidebar_id, $wp_registered_sidebars ) ) {
			WP_CLI::error( "Invalid sidebar." );
		}
	}

	/**
	 * Check whether the specified widget is on the sidebar
	 *
	 * @param string $widget_id
	 */
	private function validate_sidebar_widget( $widget_id ) {

		$sidebars_widgets = $this->wp_get_sidebars_widgets();

		$widget_exists = false;
		foreach( $sidebars_widgets as $sidebar_id => $widgets ) {

			if ( in_array( $widget_id, $widgets ) ) {
				$widget_exists = true;
				break;
			}

		}
		return $widget_exists;
	}

	/**
	 * Get the widgets (and their associated data) for a given sidebar
	 *
	 * @param string $sidebar_id
	 * @return array
	 */
	private function get_sidebar_widgets( $sidebar_id ) {

		$all_widgets = $this->wp_get_sidebars_widgets();

		if ( empty( $all_widgets[ $sidebar_id ] ) ) {
			return array();
		}

		$prepared_widgets = array();
		foreach( $all_widgets[ $sidebar_id ] as $key => $widget_id ) {

			$prepared_widget = new stdClass;

			$parts = explode( '-', $widget_id );
			$option_index = array_pop( $parts );
			$widget_name = implode( '-', $parts );

			$prepared_widget->name = $widget_name;
			$prepared_widget->id = $widget_id;
			$prepared_widget->position = $key + 1;
			$widget_options = get_option( 'widget_' . $widget_name );
			$prepared_widget->options = $widget_options[ $option_index ];

			$prepared_widgets[] = $prepared_widget;
		}

		return $prepared_widgets;
	}

	/**
	 * Re-implementation of wp_get_sidebars_widgets()
	 * because the original has a nasty global component
	 */
	private function wp_get_sidebars_widgets() {
		$sidebars_widgets = get_option( 'sidebars_widgets', array() );

		if ( is_array( $sidebars_widgets ) && isset( $sidebars_widgets['array_version'] ) ) {
			unset( $sidebars_widgets['array_version'] );
		}

		return $sidebars_widgets;
	}

	/**
	 * Get the widget's name, option index, sidebar, and sidebar index from its ID
	 *
	 * @param string $widget_id
	 * @return array
	 */
	private function get_widget_data( $widget_id ) {

		$parts = explode( '-', $widget_id );
		$option_index = array_pop( $parts );
		$name = implode( '-', $parts );

		$sidebar_id = false;
		$sidebar_index = false;
		$all_widgets = $this->wp_get_sidebars_widgets();
		foreach( $all_widgets as $s_id => &$widgets ) {

			if ( false !== ( $key = array_search( $widget_id, $widgets ) ) ) {
				$sidebar_id = $s_id;
				$sidebar_index = $key;
				break;
			}

		}

		return array( $name, $option_index, $sidebar_id, $sidebar_index );
	}

	/**
	 * Get the options for a given widget
	 *
	 * @param string $name
	 * @return array
	 */
	private function get_widget_options( $name ) {
		return get_option( 'widget_' . $name, array() );
	}

	/**
	 * Update the options for a given widget
	 *
	 * @param string $name
	 * @param mixed
	 */
	private function update_widget_options( $name, $value ) {
		update_option( 'widget_' . $name, $value );
	}

	/**
	 * Reposition a widget within a sidebar or move to another sidebar.
	 *
	 * @param string $widget_id
	 * @param string|null $current_sidebar_id
	 * @param string $new_sidebar_id
	 * @param int|null $current_index
	 * @param int $new_index
	 */
	private function move_sidebar_widget( $widget_id, $current_sidebar_id, $new_sidebar_id, $current_index, $new_index ) {

		$all_widgets = $this->wp_get_sidebars_widgets();
		$needs_placement = true;
		// Existing widget
		if ( $current_sidebar_id && ! is_null( $current_index ) ) {

			$widgets = $all_widgets[ $current_sidebar_id ];
			if ( $current_sidebar_id !== $new_sidebar_id ) {

				unset( $widgets[ $current_index ] );

			} else {

				$part = array_splice( $widgets, $current_index, 1 );
				array_splice( $widgets, $new_index, 0, $part );

				$needs_placement = false;

			}

			$all_widgets[ $current_sidebar_id ] = array_values( $widgets );

		}

		if ( $needs_placement ) {
			$widgets = ! empty( $all_widgets[ $new_sidebar_id ] ) ? $all_widgets[ $new_sidebar_id ] : array();
			$before = array_slice( $widgets, 0, $new_index, true );
			$after = array_slice( $widgets, $new_index, count( $widgets ), true );
			$widgets = array_merge( $before, array( $widget_id ), $after );
			$all_widgets[ $new_sidebar_id ] = array_values( $widgets );
		}

		update_option( 'sidebars_widgets', $all_widgets );

	}

	/**
	 * Get a widget's instantiated object based on its name
	 *
	 * @param string $id_base Name of the widget
	 * @return WP_Widget|false
	 */
	private function get_widget_obj( $id_base ) {
		global $wp_widget_factory;

		$widget = wp_filter_object_list( $wp_widget_factory->widgets, array( 'id_base' => $id_base ) );
		if ( empty( $widget ) ) {
			false;
		}

		return array_pop( $widget );
	}

	/**
	 * Clean up a widget's options based on its update callback
	 *
	 * @param string $id_base Name of the widget
	 * @param mixed $dirty_options
	 * @param mixed $old_options
	 * @return mixed
	 */
	private function sanitize_widget_options( $id_base, $dirty_options, $old_options ) {

		$widget = $this->get_widget_obj( $id_base );
		if ( empty( $widget ) ) {
			return array();
		}

		// No easy way to determine expected array keys for $dirty_options
		// because Widget API dependent on the form fields
		return @$widget->update( $dirty_options, $old_options );

	}

}

WP_CLI::add_command( 'widget', 'Widget_Command' );
