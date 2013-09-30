<?php

/**
 * Manage rewrite rules.
 *
 * @package wp-cli
 */
class Rewrite_Command extends WP_CLI_Command {

	/**
	 * Flush rewrite rules.
	 *
	 * ## OPTIONS
	 *
	 * [--hard]
	 * : Perform a hard flush - update `.htaccess` rules as well as rewrite rules in database.
	 */
	public function flush( $args, $assoc_args ) {
		// make sure we detect mod_rewrite if configured in apache_modules in config
		self::apache_modules();
		flush_rewrite_rules( isset( $assoc_args['hard'] ) );
	}

	/**
	 * Update the permalink structure.
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
	 *     wp rewrite structure '/%year%/%monthnum%/%postname%'
	 */
	public function structure( $args, $assoc_args ) {
		global $wp_rewrite;

		// copypasta from /wp-admin/options-permalink.php
		$home_path = get_home_path();
		$iis7_permalinks = iis7_supports_permalinks();

		$prefix = $blog_prefix = '';
		if ( ! got_mod_rewrite() && ! $iis7_permalinks )
			$prefix = '/index.php';
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
		flush_rewrite_rules( isset( $assoc_args['hard'] ) );
	}

	/**
	 * Print current rewrite rules.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output list as table, JSON or CSV. Defaults to table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp rewrite list --format=csv
	 * @subcommand list
	 */
	public function _list( $args, $assoc_args ) {
		$rules = get_option( 'rewrite_rules' );
		if ( ! $rules ) {
			$rules = array();
			WP_CLI::warning( 'No rewrite rules.' );
		}

		$defaults = array(
			'format' => 'table'
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$rule_list = array();
		foreach ( $rules as $match => $query ) {
			$rule_list[] = compact( 'match', 'query' );
		}

		WP_CLI\Utils\format_items( $assoc_args['format'], $rule_list, array('match', 'query') );
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
	 * 2. if the mod_rewrite module is returned from the apche_get_modules
	 *    function.
	 *
	 * To get this to work with wp-cli you'll need to add the mod_rewrite module
	 * to your config.yml. For example
	 *
	 * apache_modules:
	 *   - mod_rewrite
	 *
	 * If this isn't done then the .htaccess rewrite rules won't be flushed out
	 * to disk.
	 */
	private static function apache_modules() {
		$mods = WP_CLI::get_config('apache_modules');
		if ( !empty( $mods ) && !function_exists( 'apache_get_modules' ) ) {
			global $is_apache;
			$is_apache = true;

			function apache_get_modules() {
				return WP_CLI::get_config( 'apache_modules' );
			}
		}
	}
}

WP_CLI:: add_command( 'rewrite', 'Rewrite_Command' );

