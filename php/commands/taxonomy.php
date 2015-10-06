<?php
/**
 * Manage taxonomies.
 *
 * @package wp-cli
 */
class Taxonomy_Command extends WP_CLI_Command {

	private $fields = array(
		'name',
		'label',
		'description',
		'public',
		'hierarchical'
	);

	/**
	 * List taxonomies.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : Filter by one or more fields (see get_taxonomies() first parameter for a list of available fields).
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each taxonomy.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific taxonomy fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each term:
	 *
	 * * name
	 * * label
	 * * description
	 * * public
	 * * hierarchical
	 *
	 * There are no optionally available fields.
	 *
	 * ## EXAMPLES
	 *
	 *     wp taxonomy list --format=csv
	 *
	 *     wp taxonomy list --object-type=post --fields=name,public
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$formatter = $this->get_formatter( $assoc_args );

		if ( isset( $assoc_args['object_type'] ) ) {
			$assoc_args['object_type'] = array( $assoc_args['object_type'] );
		}

		$taxonomies = get_taxonomies( $assoc_args, 'objects' );

		$formatter->display_items( $taxonomies );
	}

	private function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->fields, 'taxonomy' );
	}
}

WP_CLI::add_command( 'taxonomy', 'Taxonomy_Command' );
