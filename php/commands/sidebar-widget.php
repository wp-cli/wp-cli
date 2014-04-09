<?php

/**
 * Manage sidebar widgets.
 */

class Widget_Command extends WP_CLI_Command {

	private $fields = array(
		'name',
		'position',
		'options',
		);

	/**
	 * List widgets associated with a sidebar.
	 * 
	 * <sidebar-id>
	 * : ID for the corresponding sidebar.
	 * 
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields. Defaults to name, id, description
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp sidebar widget list <sidebar> --fields=name --format=csv
	 *
	 * @subcommand list
	 */
	public function _list( $args, $assoc_args ) {

		list( $sidebar_id ) = $args;

		$this->validate_sidebar( $sidebar_id );

		$output_widgets = $this->get_sidebar_widgets( $sidebar_id );

		if ( empty( $assoc_args['format'] ) || in_array( $assoc_args['format'], array( 'table', 'csv') ) ) {
			foreach( $output_widgets as &$output_widget ) {
				$output_widget->options = json_encode( $output_widget->options );
			}
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields );
		$formatter->display_items( $output_widgets );

	}

	/**
	 * Add a widget to a sidebar.
	 * 
	 * <sidebar-id>
	 * : ID for the corresponding sidebar.
	 * 
	 * <name>
	 * : Widget name.
	 * 
	 * [<position>]
	 * : Widget's current position within the sidebar. Defaults to last
	 * 
	 * [--<field>=<value>]
	 * : Widget option to add, with its new value
	 * 
	 * @subcommand add
	 */
	public function add( $args, $assoc_args ) {

		list( $sidebar_id, $name ) = $args;
		$new_position = ( isset( $args[2] ) ) ? (int) $args[2] : false;
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

		$all_widgets = $this->wp_get_sidebars_widgets();
		$all_widgets[ $sidebar_id ][] = $name . '-' . $option_index;
		update_option( 'sidebars_widgets', $all_widgets );

		$current_position = count( $all_widgets[ $sidebar_id ] );
		if ( $new_position ) {
			$this->reposition_sidebar_widget( $sidebar_id, $current_position, $new_position );
		}

		WP_CLI::success( "Added widget." );

	}

	/**
	 * Update a given widget's options.
	 * 
	 * <sidebar-id>
	 * : ID for the corresponding sidebar.
	 * 
	 * <name>
	 * : Widget name.
	 * 
	 * <position>
	 * : Widget's current position within the sidebar.
	 * 
	 * [--<field>=<value>]
	 * : Field to update, with its new value
	 * 
	 * @subcommand update
	 */
	public function update( $args, $assoc_args ) {

		list( $sidebar_id, $name, $position ) = $args;
		$this->validate_sidebar_widget( $sidebar_id, $name, $position );

		if ( empty( $assoc_args ) ) {
			WP_CLI::error( "No options specified to update." );
		}

		$option_index = $this->get_widget_option_index( $sidebar_id, $name, $position );
		$widget_options = $this->get_widget_options( $name );
		$clean_options = $this->sanitize_widget_options( $name, $assoc_args, $widget_options[ $option_index ] );
		$widget_options[ $option_index ] = array_merge( (array)$widget_options[ $option_index ], $clean_options );
		$this->update_widget_options( $name, $widget_options );

		WP_CLI::success( "Widget updated." );

	}

	/**
	 * Move a widget from one position on a sidebar to another.
	 * 
	 * <sidebar-id>
	 * : ID for the corresponding sidebar.
	 * 
	 * <name>
	 * : Widget name.
	 * 
	 * <current-position>
	 * : Widget's current position within the sidebar.
	 * 
	 * <new-position>
	 * : Widget's new position within the sidebar.
	 * 
	 * @subcommand move
	 */
	public function move( $args, $assoc_args ) {

		list( $sidebar_id, $name, $current_position, $new_position ) = $args;
		$this->validate_sidebar_widget( $sidebar_id, $name, $current_position );

		if ( $new_position < -1 ) {
			$new_position = 1;
		}

		$this->reposition_sidebar_widget( $sidebar_id, $current_position, $new_position );

		WP_CLI::success( "Widget moved." );

	}

	/**
	 * Remove a widget from a sidebar.
	 * 
	 * <sidebar-id>
	 * : ID for the corresponding sidebar.
	 * 
	 * <name>
	 * : Widget name.
	 * 
	 * <position>
	 * : Widget's current position within the sidebar.
	 * 
	 * @subcommand
	 */
	public function remove( $args, $assoc_args ) {

		list( $sidebar_id, $name, $position ) = $args;
		$this->validate_sidebar_widget( $sidebar_id, $name, $position );

		// Remove the widget's settings
		$option_index = $this->get_widget_option_index( $sidebar_id, $name, $position );
		$widget_options = $this->get_widget_options( $name );
		unset( $widget_options[ $option_index ] );
		$this->update_widget_options( $name, $widget_options );

		// Remove the widget from the sidebar
		$all_widgets = $this->wp_get_sidebars_widgets();
		$position--;
		unset( $all_widgets[ $sidebar_id ][ $position ] );
		$all_widgets[ $sidebar_id ] = array_values( $all_widgets[ $sidebar_id ] );
		update_option( 'sidebars_widgets', $all_widgets );

		WP_CLI::success( "Widget removed from sidebar." );
	}

	/**
	 * Check whether a sidebar is a valid sidebar
	 * 
	 * @param string $sidebar_id
	 */
	private function validate_sidebar( $sidebar_id ) {
		global $wp_registered_sidebars;

		Sidebar_Command::register_unused_sidebar();

		if ( ! array_key_exists( $sidebar_id, $wp_registered_sidebars ) ) {
			WP_CLI::error( "Invalid sidebar." );
		}
	}

	/**
	 * Check whether the specified widget is on the sidebar
	 *
	 * @param string $sidebar_id
	 * @param string $name
	 * @param int $position
	 */
	private function validate_sidebar_widget( $sidebar_id, $name, $position ) {

		$this->validate_sidebar( $sidebar_id );

		$sidebar_widgets = $this->get_sidebar_widgets( $sidebar_id );

		$widget_exists = false;
		foreach( $sidebar_widgets as $sidebar_widget ) {

			if ( $name == $sidebar_widget->name && $position == $sidebar_widget->position ) {
				$widget_exists = true;
				break;
			}

		}

		if ( false === $widget_exists ) {
			WP_CLI::error( "Specified widget isn't present on sidebar." );
		}

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
		foreach( $all_widgets[ $sidebar_id ] as $key => $widget_name ) {

			$prepared_widget = new stdClass;

			$parts = explode( '-', $widget_name );
			$option_index = array_pop( $parts );
			$widget_name = implode( '-', $parts );

			$prepared_widget->name = $widget_name;
			$prepared_widget->position = $key + 1;
			$widget_options = get_option( 'widget_' . $widget_name );
			$prepared_widget->options = $widget_options[ $option_index ];

			$prepared_widgets[] = $prepared_widget;
		}

		return $prepared_widgets;
	}

	/**
	 * Re-implementation of wp_get_sidebars_widgets()
	 */ 
	private function wp_get_sidebars_widgets() {
		$sidebars_widgets = get_option( 'sidebars_widgets', array() );

		if ( is_array( $sidebars_widgets ) && isset( $sidebars_widgets['array_version'] ) ) {
			unset( $sidebars_widgets['array_version'] );
		}

		return $sidebars_widgets;
	}

	/**
	 * Get the widget's option index from its location on the sidebar
	 *
	 * @param string $sidebar_id
	 * @param string $name
	 * @param int $position
	 * @return int
	 */
	private function get_widget_option_index( $sidebar_id, $name, $position ) {

		$all_widgets = $this->wp_get_sidebars_widgets();
		$sidebar_widgets = $all_widgets[ $sidebar_id ];
		$position--;
		$widget_real_name = $sidebar_widgets[ $position ];
		$parts = explode( '-', $widget_real_name );
		$option_index = array_pop( $parts );

		return $option_index;
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
	 * Reposition a widget within a sidebar
	 * 
	 * @param string $sidebar_id
	 * @param int $current_position
	 * @param int $new_position
	 */
	private function reposition_sidebar_widget( $sidebar_id, $current_position, $new_position ) {

		// Human-readable positions are different than numerically indexed array
		$current_position--;
		$new_position--;

		// Reposition and update
		$all_widgets = $this->wp_get_sidebars_widgets();
		$sidebar_widgets = $all_widgets[ $sidebar_id ];
		$part = array_splice( $sidebar_widgets, $current_position, 1 );
		array_splice( $sidebar_widgets, $new_position, 0, $part );
		$all_widgets[ $sidebar_id ] = array_values( $sidebar_widgets );
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

		return $widget->update( $dirty_options, $old_options );

	}

}

WP_CLI::add_command( 'sidebar widget', 'Widget_Command' );
