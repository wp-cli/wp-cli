<?php

/**
 * Generate code for post types, taxonomies, etc.
 *
 * @package wp-cli
 */
class Scaffold_Command extends WP_CLI_Command {

	function __construct() {
		WP_Filesystem();
	}

	/**
	 * Generate PHP code for registering a custom post type.
	 *
	 * @subcommand post-type
	 *
	 * @alias cpt
	 *
	 * @synopsis <slug> [--singular=<label>] [--description=<description>] [--public=<public>] [--exclude_from_search=<exclude_from_search>] [--show_ui=<show_ui>] [--show_in_nav_menus=<show_in_nav_menus>] [--show_in_menu=<show_in_menu>] [--show_in_admin_bar=<show_in_admin_bar>] [--menu_position=<menu_position>] [--menu_icon=<menu_icon>] [--capability_type=<capability_type>] [--hierarchical=<hierarchical>] [--supports=<supports>] [--has_archive=<has_archive>] [--query_var=<query_var>] [--can_export=<can_export>] [--textdomain=<textdomain>] [--theme] [--plugin=<plugin>] [--raw]
	 */
	function post_type( $args, $assoc_args ) {
		$defaults = array(
			'description'         => '',
			'public'              => 'true',
			'exclude_from_search' => 'false',
			'show_ui'             => 'true',
			'show_in_nav_menus'   => 'true',
			'show_in_menu'        => 'true',
			'show_in_admin_bar'   => 'true',
			'menu_position'       => 'null',
			'menu_icon'           => 'null',
			'capability_type'     => 'post',
			'hierarchical'        => 'false',
			'supports'            => "'title', 'editor'",
			'has_archive'         => 'true',
			'rewrite'             => 'true',
			'query_var'           => 'true',
			'can_export'          => 'true',
			'textdomain'          => '',
		);

		$this->_scaffold( $args[0], $assoc_args, $defaults, '/post-types/', array(
			'post_type.mustache',
			'post_type_extended.mustache'
		) );
	}

	/**
	 * Generate PHP code for registering a custom taxonomy.
	 *
	 * @subcommand taxonomy
	 *
	 * @alias tax
	 *
	 * @synopsis <slug> [--singular=<label>] [--public=<public>] [--show_in_nav_menus=<show_in_nav_menus>] [--show_ui=<show_ui>] [--show_tagcloud=<show_tagcloud>] [--hierarchical=<hierarchical>]  [--rewrite=<rewrite>] [--query_var=<query_var>] [--textdomain=<textdomain>] [--post_types=<post_types>] [--theme] [--plugin=<plugin>] [--raw]
	 */
	function taxonomy( $args, $assoc_args ) {
		$defaults = array(
			'public'              => 'true',
			'show_in_nav_menus'   => 'true',
			'show_ui'             => 'true',
			'show_tagcloud'       => 'true',
			'hierarchical'        => 'false',
			'rewrite'             => 'true',
			'query_var'           => 'true',
			'post_types'          => 'post',
			'textdomain'          => '',
		);

		$this->_scaffold( $args[0], $assoc_args, $defaults, '/taxonomies/', array(
			'taxonomy.mustache',
			'taxonomy_extended.mustache'
		) );
	}

	private function _scaffold( $slug, $assoc_args, $defaults, $subdir, $templates ) {
		global $wp_filesystem;

		$control_args = $this->extract_args( $assoc_args, array(
			'theme'  => false,
			'plugin' => false,
			'raw'    => false,
		) );

		$vars = $this->extract_args( $assoc_args, $defaults );

		$vars['slug'] = $slug;

		$vars['textdomain'] = $this->get_textdomain( $vars['textdomain'], $control_args );

		// If no label is given use the slug and prettify it as good as possible
		if ( isset( $assoc_args['singular'] ) ) {
			$vars['label'] = $assoc_args['singular'];
		} else {
			$vars['label'] = preg_replace( '/_|-/', ' ', strtolower( $slug ) );
		}

		$vars['label_ucfirst']        = ucfirst( $vars['label'] );
		$vars['label_plural']         = $this->pluralize( $vars['label'] );
		$vars['label_plural_ucfirst'] = ucfirst( $vars['label_plural'] );

		// We use the machine name for function declarations
		$machine_name = preg_replace( '/-/', '_', $slug );
		$machine_name_plural = $this->pluralize( $slug );

		list( $raw_template, $extended_template ) = $templates;

		$raw_output = $this->render( $raw_template, $vars );

		if ( ! $control_args['raw'] ) {
			$vars = array_merge( $vars, array(
				'machine_name' => $machine_name,
				'output' => $raw_output
			) );

			$final_output = $this->render( $extended_template, $vars );
		} else {
			$final_output = $raw_output;
		}

		if ( $path = $this->get_output_path( $control_args, $subdir ) ) {
			$filename = $path . $machine_name .'.php';

			$this->create_file( $filename, $final_output );

			WP_CLI::success( "Created $filename" );
		} else {
			// STDOUT
			echo $final_output;
		}
	}

	private function get_output_path( $assoc_args, $subdir ) {
		extract( $assoc_args, EXTR_SKIP );

		if ( $theme ) {
			$path = TEMPLATEPATH;
		} elseif ( ! empty( $plugin ) ) {
			$path = WP_PLUGIN_DIR . '/' . $plugin;
		} else {
			return false;
		}

		$path .= $subdir;

		return $path;
	}

	/**
	 * Generate starter code for a plugin.
	 *
	 * @synopsis <slug> [--plugin_name=<title>] [--activate]
	 */
	function plugin( $args, $assoc_args ) {
		$plugin_slug = $args[0];

		$data = wp_parse_args( $assoc_args, array(
			'plugin_name' => ucfirst( $plugin_slug ),
		) );

		$data['textdomain'] = $plugin_slug;

		$plugin_contents = $this->render( 'plugin.mustache', $data );

		$plugin_path = WP_PLUGIN_DIR . "/$plugin_slug/$plugin_slug.php";

		$this->create_file( $plugin_path, $plugin_contents );

		WP_CLI::success( "Plugin scaffold created: $plugin_path" );

		if ( isset( $assoc_args['activate'] ) )
			WP_CLI::run_command( array( 'plugin', 'activate', $plugin_slug ) );
	}

	private function create_file( $filename, $contents ) {
		global $wp_filesystem;

		$wp_filesystem->mkdir( dirname( $filename ) );

		if ( !$wp_filesystem->put_contents( $filename, $contents ) ) {
			WP_CLI::error( "Error creating file: $filename" );
		}
	}

	/**
	 * If you're writing your files to your theme directory your textdomain also needs to be the same as your theme.
	 * Same goes for when plugin is being used.
	 */
	private function get_textdomain( $textdomain, $args ) {
		if ( strlen( $textdomain ) )
			return $textdomain;

		if ( $args['theme'] )
			return strtolower( wp_get_theme()->template );

		if ( $args['plugin'] && true !== $args['plugin'] )
			return $args['plugin'];

		return 'YOUR-TEXTDOMAIN';
	}

	private function pluralize( $word ) {
		$plural = array(
			'/(quiz)$/i'                => '\1zes',
			'/^(ox)$/i'                 => '\1en',
			'/([m|l])ouse$/i'           => '\1ice',
			'/(matr|vert|ind)ix|ex$/i'  => '\1ices',
			'/(x|ch|ss|sh)$/i'          => '\1es',
			'/([^aeiouy]|qu)ies$/i'     => '\1y',
			'/([^aeiouy]|qu)y$/i'       => '\1ies',
			'/(hive)$/i'                => '\1s',
			'/(?:([^f])fe|([lr])f)$/i'  => '\1\2ves',
			'/sis$/i'                   => 'ses',
			'/([ti])um$/i'              => '\1a',
			'/(buffal|tomat)o$/i'       => '\1oes',
			'/(bu)s$/i'                 => '1ses',
			'/(alias|status)/i'         => '\1es',
			'/(octop|vir)us$/i'         => '1i',
			'/(ax|test)is$/i'           => '\1es',
			'/s$/i'                     => 's',
			'/$/'                       => 's'
		);

		$uncountable = array( 'equipment', 'information', 'rice', 'money', 'species', 'series', 'fish', 'sheep' );

		$irregular = array(
			'person'    => 'people',
			'man'       => 'men',
			'woman'     => 'women',
			'child'     => 'children',
			'sex'       => 'sexes',
			'move'      => 'moves'
		);

		$lowercased_word = strtolower( $word );

		foreach ( $uncountable as $_uncountable ) {
			if ( substr( $lowercased_word, ( -1 * strlen( $_uncountable ) ) ) == $_uncountable ) {
				return $word;
			}
		}

		foreach ( $irregular as $_plural=> $_singular ) {
			if ( preg_match( '/('.$_plural.')$/i', $word, $arr ) ) {
				return preg_replace( '/('.$_plural.')$/i', substr( $arr[0], 0, 1 ).substr( $_singular, 1 ), $word );
			}
		}

		foreach ( $plural as $rule => $replacement ) {
			if ( preg_match( $rule, $word ) ) {
				return preg_replace( $rule, $replacement, $word );
			}
		}
		return false;
	}

	protected function extract_args( $assoc_args, $defaults ) {
		$out = array();

		foreach ( $defaults as $key => $value ) {
			$out[ $key ] = isset( $assoc_args[ $key ] )
				? $assoc_args[ $key ]
				: $value;
		}

		return $out;
	}

	private function render( $template, $data ) {
		$scaffolds_dir = WP_CLI_ROOT . 'templates';

		$template = file_get_contents( $scaffolds_dir . '/' . $template );

		$m = new Mustache_Engine;

		return $m->render( $template, $data );
	}
}

WP_CLI::add_command( 'scaffold', 'Scaffold_Command' );

