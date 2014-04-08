<?php

/**
 * Manage sidebars.
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
	 * : Limit the output to specific object fields. Defaults to name, id, description
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp sidebar list --fields=name,id --format=csv
	 *
	 * @subcommand list
	 */
	public function _list( $args, $assoc_args ) {
		global $wp_registered_sidebars;

		self::register_unused_sidebar();

		$output_sidebars = array();
		foreach( $wp_registered_sidebars as $registered_sidebar ) {
			$output_sidebars[] = (object)$registered_sidebar;
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields );
		$formatter->display_items( $output_sidebars );

	}

	/**
	 * Register the sidebar for unused widgets
	 * Core does this in /wp-admin/widgets.php, which isn't helpful
	 */
	public static function register_unused_sidebar() {

		register_sidebar(array(
			'name' => __('Inactive Widgets'),
			'id' => 'wp_inactive_widgets',
			'class' => 'inactive-sidebar',
			'description' => __( 'Drag widgets here to remove them from the sidebar but keep their settings.' ),
			'before_widget' => '',
			'after_widget' => '',
			'before_title' => '',
			'after_title' => '',
		));

	}

}

WP_CLI::add_command( 'sidebar', 'Sidebar_Command' );
