<?php

/**
 * Manage sidebars.
 *
 * ## EXAMPLES
 *
 *     # List sidebars
 *     $ wp sidebar list --fields=name,id --format=csv
 *     name,id
 *     "Widget Area",sidebar-1
 *     "Inactive Widgets",wp_inactive_widgets
 */
class Sidebar_Command extends WP_CLI_Command {

	private $fields = array(
		'name',
		'id',
		'description'
	);

	/**
	 * List registered sidebars.
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
	 *   - yaml
	 * ---
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each sidebar:
	 *
	 * * name
	 * * id
	 * * description
	 *
	 * These fields are optionally available:
	 *
	 * * class
	 * * before_widget
	 * * after_widget
	 * * before_title
	 * * after_title
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp sidebar list --fields=name,id --format=csv
	 *     name,id
	 *     "Widget Area",sidebar-1
	 *     "Inactive Widgets",wp_inactive_widgets
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		global $wp_registered_sidebars;

		\WP_CLI\Utils\wp_register_unused_sidebar();

		$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields );
		$formatter->display_items( $wp_registered_sidebars );
	}

	/**
	 * Reset one or all sidebars.
	 *
	 * ## OPTIONS
	 *
	 * [<sidebar-id>]
	 * : ID for the corresponding sidebar.
	 *
	 * @subcommand reset
	 */
	public function reset( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			$this->reset_all();
		} else {
			$this->reset_single( $args[0] );
		}
	}

	/**
	 * Reset all sidebars.
	 */
	private function reset_all() {
		global $wp_registered_sidebars;

		if ( ! empty( $wp_registered_sidebars ) ) {
			foreach ( $wp_registered_sidebars as $sidebar_id => $sidebar ) {
				if ( 'wp_inactive_widgets' !== $sidebar_id ) {
					$this->reset_single( $sidebar_id );
				}
			}
		}
		else {
			WP_CLI::warning( 'No registered sidebars.' );
		}
	}

	/**
	 * Reset sidebar.
	 *
	 * @param string $sidebar_id Sidebar ID.
	 */
	private function reset_single( $sidebar_id ) {
		$this->validate_sidebar( $sidebar_id );

		$widgets = $this->get_sidebar_widgets( $sidebar_id );
		if ( empty( $widgets ) ) {
			WP_CLI::warning( sprintf( "'%s' is already empty.", $sidebar_id ) );
		}
		else {
			foreach ( $widgets as $widget ) {
				$widget_id = $widget->id;
				list( $name, $option_index, $new_sidebar_id, $sidebar_index ) = $this->get_widget_data( $widget_id );
				$this->move_sidebar_widget( $widget_id, $new_sidebar_id, 'wp_inactive_widgets', $sidebar_index, 0 );
			}
			WP_CLI::success( sprintf( "Sidebar '%s' reset.", $sidebar_id ) );
		}
	}

	/**
	 * Check whether a sidebar is a valid sidebar.
	 *
	 * @param string $sidebar_id Sidebar ID.
	 */
	private function validate_sidebar( $sidebar_id ) {
		global $wp_registered_sidebars;

		\WP_CLI\Utils\wp_register_unused_sidebar();

		if ( ! array_key_exists( $sidebar_id, $wp_registered_sidebars ) ) {
			WP_CLI::error( 'Invalid sidebar.' );
		}
	}

	/**
	 * Get the widgets (and their associated data) for a given sidebar.
	 *
	 * @param string $sidebar_id Sidebar ID.
	 * @return array Widgets and their associated data.
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
	 * Re-implementation of wp_get_sidebars_widgets() because the original has
	 * a nasty global component.
	 *
	 * @return array Registered sidebars.
	 */
	private function wp_get_sidebars_widgets() {
		$sidebars_widgets = get_option( 'sidebars_widgets', array() );

		if ( is_array( $sidebars_widgets ) && isset( $sidebars_widgets['array_version'] ) ) {
			unset( $sidebars_widgets['array_version'] );
		}

		return $sidebars_widgets;
	}

	/**
	 * Get the widget's name, option index, sidebar, and sidebar index from its ID.
	 *
	 * @param string $widget_id Widget ID.
	 * @return array Widget detail.
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
	 * Reposition a widget within a sidebar or move to another sidebar.
	 *
	 * @param string      $widget_id          Widget ID.
	 * @param string|null $current_sidebar_id Current sidebar ID.
	 * @param string      $new_sidebar_id     New sidebar ID
	 * @param int|null    $current_index      Current index.
	 * @param int         $new_index          New index.
	 */
	private function move_sidebar_widget( $widget_id, $current_sidebar_id, $new_sidebar_id, $current_index, $new_index ) {

		$all_widgets = $this->wp_get_sidebars_widgets();
		$needs_placement = true;
		// Existing widget.
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

}

WP_CLI::add_command( 'sidebar', 'Sidebar_Command' );
