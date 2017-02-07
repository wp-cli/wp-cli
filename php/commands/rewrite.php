<?php

/**
 * Manage rewrite rules.
 *
 * ## EXAMPLES
 *
 *     # Flush rewrite rules
 *     $ wp rewrite flush
 *     Success: Rewrite rules flushed.
 *
 *     # Update permalink structure
 *     $ wp rewrite structure '/%year%/%monthnum%/%postname%'
 *     Success: Rewrite structure set.
 *
 *     # List rewrite rules
 *     $ wp rewrite list --format=csv
 *     match,query,source
 *     ^wp-json/?$,index.php?rest_route=/,other
 *     ^wp-json/(.*)?,index.php?rest_route=/$matches[1],other
 *     category/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$,index.php?category_name=$matches[1]&feed=$matches[2],category
 *     category/(.+?)/(feed|rdf|rss|rss2|atom)/?$,index.php?category_name=$matches[1]&feed=$matches[2],category
 *     category/(.+?)/embed/?$,index.php?category_name=$matches[1]&embed=true,category
 *
 * @package wp-cli
 */
class Rewrite_Command extends WP_CLI_Command {

	/**
	 * Flush rewrite rules.
	 *
	 * Resets WordPress' rewrite rules based on registered post types, etc.
	 *
	 * To regenerate a .htaccess file with WP-CLI, you'll need to add the mod_rewrite module
	 * to your wp-cli.yml or config.yml. For example:
	 *
	 * ```
	 * apache_modules:
	 *   - mod_rewrite
	 * ```
	 *
	 * ## OPTIONS
	 *
	 * [--hard]
	 * : Perform a hard flush - update `.htaccess` rules as well as rewrite rules in database. Works only on single site installs.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp rewrite flush
	 *     Success: Rewrite rules flushed.
	 */
	public function flush( $args, $assoc_args ) {
		// make sure we detect mod_rewrite if configured in apache_modules in config
		self::apache_modules();

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'hard' ) && ! in_array( 'mod_rewrite', (array) WP_CLI::get_config( 'apache_modules' ) ) ) {
			WP_CLI::warning( "Regenerating a .htaccess file requires special configuration. See usage docs." );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'hard' ) && is_multisite() ) {
			WP_CLI::warning( "WordPress can't generate .htaccess file for a multisite install." );
		}

		flush_rewrite_rules( \WP_CLI\Utils\get_flag_value( $assoc_args, 'hard' ) );

		if ( ! get_option( 'rewrite_rules' ) ) {
			WP_CLI::warning( "Rewrite rules are empty, possibly because of a missing permalink_structure option. Use 'wp rewrite list' to verify, or 'wp rewrite structure' to update permalink_structure." );
		} else {
			WP_CLI::success( 'Rewrite rules flushed.' );
		}
	}

	/**
	 * Update the permalink structure.
	 *
	 * Sets the post permalink structure to the specified pattern.
	 *
	 * To regenerate a .htaccess file with WP-CLI, you'll need to add
	 * the mod_rewrite module to your [WP-CLI config](http://wp-cli.org/config/).
	 * For example:
	 *
	 * ```
	 * apache_modules:
	 *   - mod_rewrite
	 * ```
	 *
	 * ## OPTIONS
	 *
	 * <permastruct>
	 * : The new permalink structure to apply.
	 *
	 * [--category-base=<base>]
	 * : Set the base for category permalinks, i.e. '/category/'.
	 *
	 * [--tag-base=<base>]
	 * : Set the base for tag permalinks, i.e. '/tag/'.
	 *
	 * [--hard]
	 * : Perform a hard flush - update `.htaccess` rules as well as rewrite rules in database.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp rewrite structure '/%year%/%monthnum%/%postname%'
	 *     Success: Rewrite structure set.
	 */
	public function structure( $args, $assoc_args ) {
		global $wp_rewrite;

		// copypasta from /wp-admin/options-permalink.php

		$prefix = $blog_prefix = '';
		if ( is_multisite() && !is_subdomain_install() && is_main_site() )
			$blog_prefix = '/blog';

		$permalink_structure = ( $args[0] == 'default' ) ? '' : $args[0];

		if ( ! empty( $permalink_structure ) ) {
			$permalink_structure = preg_replace( '#/+#', '/', '/' . str_replace( '#', '', $permalink_structure ) );
			if ( $prefix && $blog_prefix )
				$permalink_structure = $prefix . preg_replace( '#^/?index\.php#', '', $permalink_structure );
			else
				$permalink_structure = $blog_prefix . $permalink_structure;
		}
		$wp_rewrite->set_permalink_structure( $permalink_structure );

		// Update category or tag bases
		if ( isset( $assoc_args['category-base'] ) ) {

			$category_base = $assoc_args['category-base'];
			if ( ! empty( $category_base ) )
				$category_base = $blog_prefix . preg_replace('#/+#', '/', '/' . str_replace( '#', '', $category_base ) );
			$wp_rewrite->set_category_base( $category_base );
		}

		if ( isset( $assoc_args['tag-base'] ) ) {

			$tag_base = $assoc_args['tag-base'];
			if ( ! empty( $tag_base ) )
				$tag_base = $blog_prefix . preg_replace('#/+#', '/', '/' . str_replace( '#', '', $tag_base ) );
			$wp_rewrite->set_tag_base( $tag_base );
		}

		// make sure we detect mod_rewrite if configured in apache_modules in config
		self::apache_modules();

		// Launch a new process to flush rewrites because core expects flush
		// to happen after rewrites are set
		$new_assoc_args = array();
		$cmd = 'rewrite flush';
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'hard' ) ) {
			$cmd .= ' --hard';
			$new_assoc_args['hard'] = true;
			if ( ! in_array( 'mod_rewrite', (array) WP_CLI::get_config( 'apache_modules' ) ) ) {
				WP_CLI::warning( "Regenerating a .htaccess file requires special configuration. See usage docs." );
			}
		}

		$process_run = WP_CLI::runcommand( $cmd );
		if ( ! empty( $process_run->stderr ) ) {
			// Strip "Warning: "
			WP_CLI::warning( substr( $process_run->stderr, 9 ) );
		}

		WP_CLI::success( "Rewrite structure set." );
	}

	/**
	 * Get a list of the current rewrite rules.
	 *
	 * ## OPTIONS
	 *
	 * [--match=<url>]
	 * : Show rewrite rules matching a particular URL.
	 *
	 * [--source=<source>]
	 * : Show rewrite rules from a particular source.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields. Defaults to match,query,source.
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
	 * ## EXAMPLES
	 *
	 *     $ wp rewrite list --format=csv
	 *     match,query,source
	 *     ^wp-json/?$,index.php?rest_route=/,other
	 *     ^wp-json/(.*)?,index.php?rest_route=/$matches[1],other
	 *     category/(.+?)/feed/(feed|rdf|rss|rss2|atom)/?$,index.php?category_name=$matches[1]&feed=$matches[2],category
	 *     category/(.+?)/(feed|rdf|rss|rss2|atom)/?$,index.php?category_name=$matches[1]&feed=$matches[2],category
	 *     category/(.+?)/embed/?$,index.php?category_name=$matches[1]&embed=true,category
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		global $wp_rewrite;

		$rules = get_option( 'rewrite_rules' );
		if ( ! $rules ) {
			$rules = array();
			WP_CLI::warning( 'No rewrite rules.' );
		}

		$defaults = array(
			'source' => '',
			'match'  => '',
			'format' => 'table',
			'fields' => 'match,query,source',
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$rewrite_rules_by_source = array();
		$rewrite_rules_by_source['post'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->permalink_structure, EP_PERMALINK );
		$rewrite_rules_by_source['date'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_date_permastruct(), EP_DATE );
		$rewrite_rules_by_source['root'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->root . '/', EP_ROOT );
		$rewrite_rules_by_source['comments'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->root . $wp_rewrite->comments_base, EP_COMMENTS, true, true, true, false );
		$rewrite_rules_by_source['search'] = $wp_rewrite->generate_rewrite_rules( $wp_rewrite->get_search_permastruct(), EP_SEARCH );
		$rewrite_rules_by_source['author'] = $wp_rewrite->generate_rewrite_rules($wp_rewrite->get_author_permastruct(), EP_AUTHORS );
		$rewrite_rules_by_source['page'] = $wp_rewrite->page_rewrite_rules();

		// Extra permastructs including tags, categories, etc.
		foreach ( $wp_rewrite->extra_permastructs as $permastructname => $permastruct ) {
			if ( is_array( $permastruct ) ) {
				$rewrite_rules_by_source[$permastructname] = $wp_rewrite->generate_rewrite_rules( $permastruct['struct'], $permastruct['ep_mask'], $permastruct['paged'], $permastruct['feed'], $permastruct['forcomments'], $permastruct['walk_dirs'], $permastruct['endpoints'] );
			} else {
				$rewrite_rules_by_source[$permastructname] = $wp_rewrite->generate_rewrite_rules( $permastruct, EP_NONE );
			}
		}

		// Apply the filters used in core just in case
		foreach( $rewrite_rules_by_source as $source => $source_rules ) {
			$rewrite_rules_by_source[$source] = apply_filters( $source . '_rewrite_rules', $source_rules );
			if ( 'post_tag' == $source )
				$rewrite_rules_by_source[$source] = apply_filters( 'tag_rewrite_rules', $source_rules );
		}

		$rule_list = array();
		foreach ( $rules as $match => $query ) {

			if ( ! empty( $assoc_args['match'] )
				&& ! preg_match( "!^$match!", trim( $assoc_args['match'], '/' ) ) )
				continue;

			$source = 'other';
			foreach( $rewrite_rules_by_source as $rules_source => $source_rules ) {
				if ( array_key_exists( $match, $source_rules ) ) {
					$source = $rules_source;
				}
			}

			if ( ! empty( $assoc_args['source'] ) && $source != $assoc_args['source'] )
				continue;

			$rule_list[] = compact( 'match', 'query', 'source' );
		}

		WP_CLI\Utils\format_items( $assoc_args['format'], $rule_list, explode( ',', $assoc_args['fields'] ) );
	}

	/**
	 * Expose apache modules if present in config
	 *
	 * Implementation Notes: This function exposes a global function
	 * apache_get_modules and also sets the $is_apache global variable.
	 *
	 * This is so that flush_rewrite_rules will actually write out the
	 * .htaccess file for apache wordpress installations. There is a check
	 * to see:
	 *
	 * 1. if the $is_apache variable is set.
	 * 2. if the mod_rewrite module is returned from the apache_get_modules
	 *    function.
	 *
	 * To get this to work with wp-cli you'll need to add the mod_rewrite module
	 * to your config.yml. For example
	 *
	 * ```
	 * apache_modules:
	 *   - mod_rewrite
	 * ```
	 *
	 * If this isn't done then the .htaccess rewrite rules won't be flushed out
	 * to disk.
	 */
	private static function apache_modules() {
		$mods = WP_CLI::get_config('apache_modules');
		if ( !empty( $mods ) && !function_exists( 'apache_get_modules' ) ) {
			global $is_apache;
			$is_apache = true;

			// needed for get_home_path() and .htaccess location
			$_SERVER['SCRIPT_FILENAME'] = ABSPATH;

			function apache_get_modules() {
				return WP_CLI::get_config( 'apache_modules' );
			}
		}
	}
}

WP_CLI::add_command( 'rewrite', 'Rewrite_Command' );

