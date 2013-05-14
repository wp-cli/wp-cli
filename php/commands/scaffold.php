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
	 * @synopsis <slug> [--label=<label>] [--textdomain=<textdomain>] [--theme] [--plugin=<plugin>] [--raw]
	 */
	function post_type( $args, $assoc_args ) {
		$defaults = array(
			'textdomain' => '',
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
	 * @synopsis <slug> [--post_types=<post-types>] [--label=<label>] [--textdomain=<textdomain>] [--theme] [--plugin=<plugin>] [--raw]
	 */
	function taxonomy( $args, $assoc_args ) {
		$defaults = array(
			'textdomain' => '',
			'post_types' => 'post'
		);

		$this->_scaffold( $args[0], $assoc_args, $defaults, '/taxonomies/', array(
			'taxonomy.mustache',
			'taxonomy_extended.mustache'
		) );
	}

	private function _scaffold( $slug, $assoc_args, $defaults, $subdir, $templates ) {
		global $wp_filesystem;

		$control_args = $this->extract_args( $assoc_args, array(
			'label'  => preg_replace( '/_|-/', ' ', strtolower( $slug ) ),
			'theme'  => false,
			'plugin' => false,
			'raw'    => false,
		) );

		$vars = $this->extract_args( $assoc_args, $defaults );

		$vars['slug'] = $slug;

		$vars['textdomain'] = $this->get_textdomain( $vars['textdomain'], $control_args );

		$vars['label'] = $control_args['label'];

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
			$filename = $path . $slug .'.php';

			$this->create_file( $filename, $final_output );

			WP_CLI::success( "Created $filename" );
		} else {
			// STDOUT
			echo $final_output;
		}
	}

	/**
	 * Generate starter code for a theme.
	 *
	 * @synopsis <slug> [--theme_name=<title>] [--author=<full-name>] [--author_uri=<http-url>] [--activate]
	 */
	function _s( $args, $assoc_args ) {

		$theme_slug = $args[0];
		$theme_path = WP_CONTENT_DIR . "/themes";
		$url = "http://underscores.me";
		$timeout = 30;

		$data = wp_parse_args( $assoc_args, array(
			'theme_name' => ucfirst( $theme_slug ),
			'author' => "Me",
			'author_uri' => "",
		) );

		$theme_description = "Custom theme: ".$data['theme_name']." developed by, ".$data['author'];

		$body['underscoresme_name'] = $data['theme_name'];
		$body['underscoresme_slug'] = $theme_slug;
		$body['underscoresme_author'] = $data['author'];
		$body['underscoresme_author_uri'] = $data['author_uri'];
		$body['underscoresme_description'] = $theme_description;
		$body['underscoresme_generate_submit'] = "Generate";
		$body['underscoresme_generate'] = "1";

		$tmpfname = wp_tempnam($url);
		$response = wp_remote_post( $url, array( 'timeout' => $timeout, 'body' => $body, 'stream' => true, 'filename' => $tmpfname ) );

		if ( $response['response']['code'] == 200 )
			WP_CLI::success( "Created theme '".$data['theme_name']."'." );

		unzip_file( $tmpfname, $theme_path );
		unlink( $tmpfname );

		if ( isset( $assoc_args['activate'] ) )
			WP_CLI::run_command( array( 'theme', 'activate', $theme_slug ) );

	}

	/**
	 * Generate empty child theme.
	 *
	 * @subcommand child-theme
	 *
	 * @synopsis <slug> --parent_theme=<slug> [--theme_name=<title>] [--author=<full-name>] [--author_uri=<http-url>] [--theme_uri=<http-url>] [--activate]
	 */
	function child_theme( $args, $assoc_args ) {
		$theme_slug = $args[0];

		$data = wp_parse_args( $assoc_args, array(
			'theme_name' => ucfirst( $theme_slug ),
			'author' => "Me",
			'author_uri' => "",
			'theme_uri' => ""
		) );

		$data['description'] = ucfirst( $data['parent_theme'] ) . " child theme.";

		$theme_dir = WP_CONTENT_DIR . "/themes" . "/$theme_slug";
		$theme_style_path = "$theme_dir/style.css";

		$this->create_file( $theme_style_path, $this->render( 'child_theme.mustache', $data ) );

		WP_CLI::success( "Created $theme_dir" );

		if ( isset( $assoc_args['activate'] ) )
			WP_CLI::run_command( array( 'theme', 'activate', $theme_slug ) );
	}

	private function get_output_path( $assoc_args, $subdir ) {
		extract( $assoc_args, EXTR_SKIP );

		if ( $theme ) {
			$path = TEMPLATEPATH;
		} elseif ( ! empty( $plugin ) ) {
			$path = WP_PLUGIN_DIR . '/' . $plugin;
			if ( !is_dir( $path ) ) {
				WP_CLI::error( "Can't find '$plugin' plugin." );
			}
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

		$plugin_dir = WP_PLUGIN_DIR . "/$plugin_slug";
		$plugin_path = "$plugin_dir/$plugin_slug.php";

		$this->create_file( $plugin_path, $this->render( 'plugin.mustache', $data ) );

		WP_CLI::success( "Created $plugin_dir" );

		WP_CLI::run_command( array( 'scaffold', 'plugin-tests', $plugin_slug ) );

		if ( isset( $assoc_args['activate'] ) )
			WP_CLI::run_command( array( 'plugin', 'activate', $plugin_slug ) );
	}

	/**
	 * Generate files needed for running PHPUnit tests.
	 *
	 * @subcommand plugin-tests
	 *
	 * @synopsis <plugin>
	 */
	function plugin_tests( $args, $assoc_args ) {
		global $wp_filesystem;

		$plugin_slug = $args[0];

		$plugin_dir = WP_PLUGIN_DIR . "/$plugin_slug";

		$tests_dir = "$plugin_dir/tests";

		$wp_filesystem->mkdir( $tests_dir );

		$this->create_file( "$tests_dir/bootstrap.php",
			$this->render( 'bootstrap.mustache', compact( 'plugin_slug' ) ) );

		$to_copy = array(
			'phpunit.xml' => $plugin_dir,
			'.travis.yml' => $plugin_dir,
			'test-sample.php' => $tests_dir,
		);

		foreach ( $to_copy as $file => $dir ) {
			$wp_filesystem->copy( $this->get_template_path( $file ), "$dir/$file", true );
		}

		WP_CLI::success( "Created test files in $plugin_dir" );
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
		$template = file_get_contents( $this->get_template_path( $template ) );

		$m = new Mustache_Engine;

		return $m->render( $template, $data );
	}

	private function get_template_path( $template ) {
		return WP_CLI_ROOT . "../templates/$template";
	}
}

WP_CLI::add_command( 'scaffold', 'Scaffold_Command' );

