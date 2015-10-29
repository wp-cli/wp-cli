<?php
/**
 * Manage post types.
 *
 * @package wp-cli
 */
class Post_Type_Command extends WP_CLI_Command {

	private $fields = array(
		'name',
		'label',
		'description',
		'hierarchical',
		'public',
		'capability_type',
	);

	/**
	 * List post types.
	 *
	 * ## OPTIONS
	 *
	 * [--<field>=<value>]
	 * : Filter by one or more fields (see get_post_types() first parameter for a list of available fields).
	 *
	 * [--field=<field>]
	 * : Prints the value of a single field for each post type.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific post type fields.
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
	 * * hierarchical
	 * * public
	 * * capability_type
	 *
	 * There are no optionally available fields.
	 *
	 * ## EXAMPLES
	 *
	 *     wp post-type list --format=csv
	 *
	 *     wp post-type list --object-type=post --fields=name,public
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$formatter = $this->get_formatter( $assoc_args );

		$types = get_post_types( $assoc_args, 'objects' );

		$formatter->display_items( $types );
	}

	/**
	 * Get a post type
	 *
	 * ## OPTIONS
	 *
	 * <post-type>
	 * : Post type slug
	 *
	 * [--field=<field>]
	 * : Instead of returning the whole taxonomy, returns the value of a single field.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Defaults to all fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     wp post-type get page --format=json
	 */
	public function get( $args, $assoc_args ) {
		$post_type = get_post_type_object( $args[0] );

		if ( ! $post_type ) {
			WP_CLI::error( "Post type {$args[0]} doesn't exist." );
		}

		if ( empty( $assoc_args['fields'] ) ) {
			$default_fields = array_merge( $this->fields, array(
				'labels',
				'cap'
			) );

			$assoc_args['fields'] = $default_fields;
		}

		$data = array(
			'name'            => $post_type->name,
			'label'           => $post_type->label,
			'description'     => $post_type->description,
			'hierarchical'    => $post_type->hierarchical,
			'public'          => $post_type->public,
			'capability_type' => $post_type->capability_type,
			'labels'          => $post_type->labels,
			'cap'             => $post_type->cap,
		);

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $data );
	}

	private function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->fields, 'post-type' );
	}
}

WP_CLI::add_command( 'post-type', 'Post_Type_Command' );
