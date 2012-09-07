<?php

WP_CLI:: add_command('rewrite', 'Rewrite_Command');

/**
 * Implement rewrite command
 *
 * @package	wp-cli
 * @subpackage	commands/internal
 */
class Rewrite_Command extends WP_CLI_Command {

	protected $aliases = array(
	);

	/**
	 * Flush rules
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function flush( $args, $assoc_args ) {

		$verbose = ( isset( $assoc_args['verbose'] ) );
		$hard = ( isset( $assoc_args['soft'] ) ) ? false : true;

		if ( $verbose )
			WP_CLI::line( "Triggering ".( ( $hard ) ? 'hard' : 'soft' ). " permalink flush." );

		flush_rewrite_rules( $hard );
	}

	/**
	 * Set permalink structure
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function structure( $args, $assoc_args ) {
		if ( !count( $args ) && !count( $assoc_args ) ) {
			WP_CLI::line( "usage: wp rewrite structure <new-permalink-structure>" );
			exit;
		}
		global $wp_rewrite;

		// copypasta from /wp-admin/options-permalink.php
		$home_path = get_home_path();
		$iis7_permalinks = iis7_supports_permalinks();

		$prefix = $blog_prefix = '';
		if ( ! got_mod_rewrite() && ! $iis7_permalinks )
			$prefix = '/index.php';
		if ( is_multisite() && !is_subdomain_install() && is_main_site() )
			$blog_prefix = '/blog';

		$verbose = ( isset( $assoc_args['verbose'] ) );


		// Update base permastruct if argument is provided
		if ( isset( $args[0] ) ) {

			if ( $verbose )
				WP_CLI::line( "Setting permalink structure to ". $args[0] );

			$permalink_structure = ( $args[0] == 'default' ) ? '' : $args[0];

			if ( ! empty( $permalink_structure ) ) {
				$permalink_structure = preg_replace( '#/+#', '/', '/' . str_replace( '#', '', $permalink_structure ) );
				if ( $prefix && $blog_prefix )
					$permalink_structure = $prefix . preg_replace( '#^/?index\.php#', '', $permalink_structure );
				else
					$permalink_structure = $blog_prefix . $permalink_structure;
			}
			$wp_rewrite->set_permalink_structure( $permalink_structure );
		}

		// Update category or tag bases
		if ( isset( $assoc_args['category-base'] ) ) {

			if ( $verbose )
				WP_CLI::line( "Setting category base to ". $assoc_args['category-base'] );

			$category_base = $assoc_args['category-base'];
			if ( ! empty( $category_base ) )
				$category_base = $blog_prefix . preg_replace('#/+#', '/', '/' . str_replace( '#', '', $category_base ) );
			$wp_rewrite->set_category_base( $category_base );
		}

		if ( isset( $assoc_args['tag-base'] ) ) {

			if ( $verbose )
				WP_CLI::line( "Setting tag base to ". $assoc_args['tag-base'] );

			$tag_base = $assoc_args['tag-base'];
			if ( ! empty( $tag_base ) )
				$tag_base = $blog_prefix . preg_replace('#/+#', '/', '/' . str_replace( '#', '', $tag_base ) );
			$wp_rewrite->set_tag_base( $tag_base );
		}


		flush_rewrite_rules( $hard );
	}

	/**
	 * Dump rewrite rules
	 *
	 * @param none
	 */
	public function dump() {

		$rules = get_option( 'rewrite_rules' );

		foreach ( $rules as $route => $rule )
			WP_CLI::line( $route . "\t" . $rule );

	}
}

?>
