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
	 * @synopsis [--hard]
	 */
	public function flush( $args, $assoc_args ) {
		// make sure we detect mod_rewrite if configured in apache_modules in config
		self::apache_modules();
		flush_rewrite_rules( isset( $assoc_args['hard'] ) );
	}

	/**
	 * Update the permalink structure.
	 *
	 * @synopsis <permastruct> [--category-base=<base>] [--tag-base=<base>] [--hard]
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
	 * @synopsis [--json]
	 */
	public function dump( $args, $assoc_args ) {

		$rules = get_option( 'rewrite_rules' );
		if ( ! $rules ) {
			$rules = array();
			WP_CLI::warning( 'No rewrite rules.' );
		}

		if ( isset( $assoc_args['json'] ) ) {
			echo json_encode( $rules );
		} else {
			foreach ( $rules as $route => $rule )
				WP_CLI::line( $route . "\t" . $rule );
		}

	}

	/**
	 * Expose apache modules if present in config
	 */
	public static function apache_modules() {
		$mods = WP_CLI::get_config('apache_modules');
		if ( count($mods) > 0 && !function_exists( 'apache_get_modules') ) {
			global $is_apache;
			$is_apache = true;

			function apache_get_modules() {
				return WP_CLI::get_config('apache_modules');
			}
		}
	}

}

WP_CLI:: add_command( 'rewrite', 'Rewrite_Command' );

