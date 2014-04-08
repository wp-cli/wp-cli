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

		$option_key = 'widget_' . $name;
		$option_index = $this->get_widget_option_index( $sidebar_id, $name, $position );
		$widget_options = get_option( $option_key );
		if ( ! isset( $widget_options[ $option_index ] ) ) {
			$widget_options[ $option_index ] = $assoc_args;
		} else {
			$widget_options[ $option_index ] = array_merge( $widget_options[ $option_index ], $assoc_args );
		}
		update_option( $option_key, $widget_options );

		WP_CLI::success( "Widget updated." );

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
		$option_key = 'widget_' . $name;
		$option_index = $this->get_widget_option_index( $sidebar_id, $name, $position );
		$widget_options = get_option( $option_key );
		unset( $widget_options[ $option_index ] );
		update_option( $option_key, $widget_options );

		// Remove the widget from the sidebar
		$all_widgets = wp_get_sidebars_widgets();
		$position--;
		unset( $all_widgets[ $sidebar_id ][ $position ] );
		update_option( 'sidebars_widgets', $all_widgets );

		// Reset the global just in case
		wp_get_sidebars_widgets();

		WP_CLI::success( "Widget removed from sidebar." );
	}

	/**
	 * Check whether a sidebar is a valid sidebar
	 * 
	 * @param string $sidebar_id
	 */
	private function validate_sidebar( $sidebar_id ) {
		global $wp_registered_sidebars;

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

		$all_widgets = wp_get_sidebars_widgets();

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
	 * Get the widget's option index from its location on the sidebar
	 *
	 * @param string $sidebar_id
	 * @param string $name
	 * @param int $position
	 * @return int
	 */
	private function get_widget_option_index( $sidebar_id, $name, $position ) {

		$all_widgets = wp_get_sidebars_widgets();
		$sidebar_widgets = $all_widgets[ $sidebar_id ];
		$position--;
		$widget_real_name = $sidebar_widgets[ $position ];
		$parts = explode( '-', $widget_real_name );
		$option_index = array_pop( $parts );

		return $option_index;
	}

}

WP_CLI::add_command( 'sidebar widget', 'Widget_Command' );
