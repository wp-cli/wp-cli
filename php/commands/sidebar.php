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

		$output_sidebars = array();
		foreach( $wp_registered_sidebars as $registered_sidebar ) {
			$output_sidebars[] = (object)$registered_sidebar;
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields );
		$formatter->display_items( $output_sidebars );

	}

}

WP_CLI::add_command( 'sidebar', 'Sidebar_Command' );
