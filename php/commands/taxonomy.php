<?php
/**
 * Manage taxonomies.
 *
 * ## EXAMPLES
 *
 *     # List all taxonomies with 'post' object type
 *     $ wp taxonomy list --object_type=post --fields=name,public
 *     +-------------+--------+
 *     | name        | public |
 *     +-------------+--------+
 *     | category    | 1      |
 *     | post_tag    | 1      |
 *     | post_format | 1      |
 *     +-------------+--------+
 *
 *     # Get capabilities of a taxonomy
 *     $ wp taxonomy get post_tag --field=cap
 *     {"manage_terms":"manage_categories","edit_terms":"manage_categories","delete_terms":"manage_categories","assign_terms":"edit_posts"}
 *
 * @package wp-cli
 */
class Taxonomy_Command extends WP_CLI_Command {

	private $fields = array(
		'name',
		'label',
		'description',
		'object_type',
		'show_tagcloud',
		'hierarchical',
		'public',
	);

	public function __construct() {

		if ( \WP_CLI\Utils\wp_version_compare( 3.7, '<' ) ) {
			// remove description for wp <= 3.7
			$this->fields = array_values( array_diff( $this->fields, array( 'description' ) ) );
		}

		parent::__construct();
	}

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
	 * * public
	 * * hierarchical
	 *
	 * There are no optionally available fields.
	 *
	 * ## EXAMPLES
	 *
	 *     # List all taxonomies
	 *     $ wp taxonomy list --format=csv
	 *     name,label,description,object_type,show_tagcloud,hierarchical,public
	 *     category,Categories,,post,1,1,1
	 *     post_tag,Tags,,post,1,,1
	 *     nav_menu,"Navigation Menus",,nav_menu_item,,,
	 *     link_category,"Link Categories",,link,1,,
	 *     post_format,Format,,post,,,1
	 *
	 *     # List all taxonomies with 'post' object type
	 *     $ wp taxonomy list --object_type=post --fields=name,public
	 *     +-------------+--------+
	 *     | name        | public |
	 *     +-------------+--------+
	 *     | category    | 1      |
	 *     | post_tag    | 1      |
	 *     | post_format | 1      |
	 *     +-------------+--------+
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$formatter = $this->get_formatter( $assoc_args );

		if ( isset( $assoc_args['object_type'] ) ) {
			$assoc_args['object_type'] = array( $assoc_args['object_type'] );
		}

		$taxonomies = get_taxonomies( $assoc_args, 'objects' );

		$taxonomies = array_map( function( $taxonomy ) {
			$taxonomy->object_type = implode( ', ', $taxonomy->object_type );
			return $taxonomy;
		}, $taxonomies );

		$formatter->display_items( $taxonomies );
	}

	/**
	 * Get a taxonomy
	 *
	 * ## OPTIONS
	 *
	 * <taxonomy>
	 * : Taxonomy slug
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
	 *     $ wp taxonomy get category
	 *     +---------------+---------------------------------------------------+
	 *     | Field         | Value                                             |
	 *     +---------------+---------------------------------------------------+
	 *     | name          | category                                          |
	 *     | label         | Categories                                        |
	 *     | description   |                                                   |
	 *     | object_type   | ["post"]                                          |
	 *     | show_tagcloud | true                                              |
	 *     | hierarchical  | true                                              |
	 *     | public        | true                                              |
	 *     | labels        | {"name":"Categories","singular_name":"Category"," |
	 *     |               | search_items":"Search Categories","popular_items" |
	 *     |               | :null,"all_items":"All Categories","parent_item": |
	 *     |               | "Parent Category","parent_item_colon":"Parent Cat |
	 *     |               | egory:","edit_item":"Edit Category","view_item":" |
	 *     |               | View Category","update_item":"Update Category","a |
	 *     |               | dd_new_item":"Add New Category","new_item_name":" |
	 *     |               | New Category Name","separate_items_with_commas":n |
	 *     |               | ull,"add_or_remove_items":null,"choose_from_most_ |
	 *     |               | used":null,"not_found":"No categories found.","no |
	 *     |               | _terms":"No categories","items_list_navigation":" |
	 *     |               | Categories list navigation","items_list":"Categor |
	 *     |               | ies list","menu_name":"Categories","name_admin_ba |
	 *     |               | r":"category"}                                    |
	 *     | cap           | {"manage_terms":"manage_categories","edit_terms": |
	 *     |               | "manage_categories","delete_terms":"manage_catego |
	 *     |               | ries","assign_terms":"edit_posts"}                |
	 *     +---------------+---------------------------------------------------+
	 *
	 *     $ wp taxonomy get post_tag --field=cap
	 *     {"manage_terms":"manage_categories","edit_terms":"manage_categories","delete_terms":"manage_categories","assign_terms":"edit_posts"}
	 */
	public function get( $args, $assoc_args ) {
		$taxonomy = get_taxonomy( $args[0] );

		if ( ! $taxonomy ) {
			WP_CLI::error( "Taxonomy {$args[0]} doesn't exist." );
		}

		if ( empty( $assoc_args['fields'] ) ) {
			$default_fields = array_merge( $this->fields, array(
				'labels',
				'cap'
			) );

			$assoc_args['fields'] = $default_fields;
		}

		$data = array(
			'name'          => $taxonomy->name,
			'label'         => $taxonomy->label,
			'description'   => $taxonomy->description,
			'object_type'   => $taxonomy->object_type,
			'show_tagcloud' => $taxonomy->show_tagcloud,
			'hierarchical'  => $taxonomy->hierarchical,
			'public'        => $taxonomy->public,
			'labels'        => $taxonomy->labels,
			'cap'           => $taxonomy->cap,
		);

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_item( $data );
	}

	private function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->fields, 'taxonomy' );
	}
}

WP_CLI::add_command( 'taxonomy', 'Taxonomy_Command' );
