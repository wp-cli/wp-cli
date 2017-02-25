<?php
/**
 * Manage post types.
 *
 * ## EXAMPLES
 *
 *     # Get details about a post type
 *     $ wp post-type get page --fields=name,label,hierarchical --format=json
 *     {"name":"page","label":"Pages","hierarchical":true}
 *
 *     # List post types with 'post' capability type
 *     $ wp post-type list --capability_type=post --fields=name,public
 *     +---------------+--------+
 *     | name          | public |
 *     +---------------+--------+
 *     | post          | 1      |
 *     | attachment    | 1      |
 *     | revision      |        |
 *     | nav_menu_item |        |
 *     +---------------+--------+
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
	 * List registered post types.
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
	 *     # List registered post types
	 *     $ wp post-type list --format=csv
	 *     name,label,description,hierarchical,public,capability_type
	 *     post,Posts,,,1,post
	 *     page,Pages,,1,1,page
	 *     attachment,Media,,,1,post
	 *     revision,Revisions,,,,post
	 *     nav_menu_item,"Navigation Menu Items",,,,post
	 *
	 *     # List post types with 'post' capability type
	 *     $ wp post-type list --capability_type=post --fields=name,public
	 *     +---------------+--------+
	 *     | name          | public |
	 *     +---------------+--------+
	 *     | post          | 1      |
	 *     | attachment    | 1      |
	 *     | revision      |        |
	 *     | nav_menu_item |        |
	 *     +---------------+--------+
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$formatter = $this->get_formatter( $assoc_args );

		$types = get_post_types( $assoc_args, 'objects' );

		$formatter->display_items( $types );
	}

	/**
	 * Get details about a registered post type.
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
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Get details about the 'page' post type.
	 *     $ wp post-type get page --fields=name,label,hierarchical --format=json
	 *     {"name":"page","label":"Pages","hierarchical":true}
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
