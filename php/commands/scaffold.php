<?php

use WP_CLI\Utils;
use WP_CLI\Process;

/**
 * Generate code for post types, taxonomies, etc.
 *
 * @package wp-cli
 */
class Scaffold_Command extends WP_CLI_Command {

	/**
	 * Generate PHP code for registering a custom post type.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The internal name of the post type.
	 *
	 * [--label=<label>]
	 * : The text used to translate the update messages
	 *
	 * [--textdomain=<textdomain>]
	 * : The textdomain to use for the labels.
	 *
	 * [--theme]
	 * : Create a file in the active theme directory, instead of sending to
	 * STDOUT. Specify a theme with `--theme=<theme>` to have the file placed in that theme.
	 *
	 * [--plugin=<plugin>]
	 * : Create a file in the given plugin's directory, instead of sending to STDOUT.
	 *
	 * [--raw]
	 * : Just generate the `register_post_type()` call and nothing else.
	 *
	 * @subcommand post-type
	 *
	 * @alias cpt
	 */
	function post_type( $args, $assoc_args ) {

		if ( strlen( $args[0] ) > 20 ) {
			WP_CLI::error( "Post type slugs cannot exceed 20 characters in length." );
		}

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
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The internal name of the taxonomy.
	 *
	 * [--post_types=<post-types>]
	 * : Post types to register for use with the taxonomy.
	 *
	 * [--label=<label>]
	 * : The text used to translate the update messages.
	 *
	 * [--textdomain=<textdomain>]
	 * : The textdomain to use for the labels.
	 *
	 * [--theme]
	 * : Create a file in the active theme directory, instead of sending to
	 * STDOUT. Specify a theme with `--theme=<theme>` to have the file placed in that theme.
	 *
	 * [--plugin=<plugin>]
	 * : Create a file in the given plugin's directory, instead of sending to STDOUT.
	 *
	 * [--raw]
	 * : Just generate the `register_taxonomy()` call and nothing else.
	 *
	 * ## EXAMPLES
	 *
	 *     wp scaffold taxonomy venue --post_types=event,presentation
	 *
	 * @subcommand taxonomy
	 *
	 * @alias tax
	 */
	function taxonomy( $args, $assoc_args ) {
		$defaults = array(
			'textdomain' => '',
			'post_types' => "'post'"
		);

		if( isset($assoc_args['post_types']) ) {
			$assoc_args['post_types'] = $this->quote_comma_list_elements( $assoc_args['post_types'] );
		}

		$this->_scaffold( $args[0], $assoc_args, $defaults, '/taxonomies/', array(
			'taxonomy.mustache',
			'taxonomy_extended.mustache'
		) );
	}

	private function _scaffold( $slug, $assoc_args, $defaults, $subdir, $templates ) {
		$wp_filesystem = $this->init_wp_filesystem();

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

		$raw_output = Utils\mustache_render( $raw_template, $vars );

		if ( ! $control_args['raw'] ) {
			$vars = array_merge( $vars, array(
				'machine_name' => $machine_name,
				'output' => $raw_output
			) );

			$final_output = Utils\mustache_render( $extended_template, $vars );
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
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The slug for the new theme, used for prefixing functions.
	 *
	 * [--activate]
	 * : Activate the newly downloaded theme.
	 *
	 * [--theme_name=<title>]
	 * : What to put in the 'Theme Name:' header in style.css
	 *
	 * [--author=<full-name>]
	 * : What to put in the 'Author:' header in style.css
	 *
	 * [--author_uri=<uri>]
	 * : What to put in the 'Author URI:' header in style.css
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

		$body = array();
		$body['underscoresme_name'] = $data['theme_name'];
		$body['underscoresme_slug'] = $theme_slug;
		$body['underscoresme_author'] = $data['author'];
		$body['underscoresme_author_uri'] = $data['author_uri'];
		$body['underscoresme_description'] = $theme_description;
		$body['underscoresme_generate_submit'] = "Generate";
		$body['underscoresme_generate'] = "1";

		$tmpfname = wp_tempnam($url);
		$response = wp_remote_post( $url, array( 'timeout' => $timeout, 'body' => $body, 'stream' => true, 'filename' => $tmpfname ) );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( $response );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 != $response_code ) {
			WP_CLI::error( "Couldn't create theme (received $response_code response)." );
		}

		$this->maybe_create_themes_dir();

		$this->init_wp_filesystem();
		unzip_file( $tmpfname, $theme_path );
		unlink( $tmpfname );

		WP_CLI::success( "Created theme '{$data['theme_name']}'." );

		if ( isset( $assoc_args['activate'] ) )
			WP_CLI::run_command( array( 'theme', 'activate', $theme_slug ) );

	}

	/**
	 * Generate empty child theme.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The slug for the new child theme.
	 *
	 * --parent_theme=<slug>
	 * : What to put in the 'Template:' header in style.css
	 *
	 * [--theme_name=<title>]
	 * : What to put in the 'Theme Name:' header in style.css
	 *
	 * [--author=<full-name>]
	 * : What to put in the 'Author:' header in style.css
	 *
	 * [--author_uri=<uri>]
	 * : What to put in the 'Author URI:' header in style.css
	 *
	 * [--theme_uri=<uri>]
	 * : What to put in the 'Theme URI:' header in style.css
	 *
	 * [--activate]
	 * : Activate the newly created child theme.
	 *
	 * @subcommand child-theme
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

		$this->maybe_create_themes_dir();

		$this->create_file( $theme_style_path, Utils\mustache_render( 'child_theme.mustache', $data ) );

		WP_CLI::success( "Created $theme_dir" );

		if ( isset( $assoc_args['activate'] ) )
			WP_CLI::run_command( array( 'theme', 'activate', $theme_slug ) );
	}

	private function get_output_path( $assoc_args, $subdir ) {
		if ( $assoc_args['theme'] ) {
			$theme = $assoc_args['theme'];
			if ( is_string( $theme ) )
				$path = get_theme_root( $theme ) . '/' . $theme;
			else
				$path = get_stylesheet_directory();
		} elseif ( $assoc_args['plugin'] ) {
			$plugin = $assoc_args['plugin'];
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
	 * Generate files needed for writing Behat tests for your command.
	 *
	 * ## DESCRIPTION
	 *
	 * These are the files that are generated:
	 *
	 * * `.travis.yml` is the configuration file for Travis CI
	 * * `bin/install-package-tests.sh` will configure environment to run tests. Script expects WP_CLI_BIN_DIR and WP_CLI_CONFIG_PATH environment variables.
	 * * `features/load-wp-cli.feature` is a basic test to confirm WP-CLI can load.
	 * * `features/bootstrap`, `features/steps`, `features/extra` are Behat configuration files.
	 * * `utils/generate-package-require-from-composer.php` generates a test config.yml file from your package's composer.json
	 *
	 * ## ENVIRONMENT
	 *
	 * The `features/bootstrap/FeatureContext.php` file expects the WP_CLI_BIN_DIR and WP_CLI_CONFIG_PATH environment variables.
	 *
	 * WP-CLI Behat framework uses Behat ~2.5.
	 *
	 * ## OPTIONS
	 *
	 * <dir>
	 * : The package directory to generate tests for.
	 *
	 * ## EXAMPLE
	 *
	 *     wp scaffold package-tests /path/to/command/dir/
	 *
	 * @when before_wp_load
	 * @subcommand package-tests
	 */
	public function package_tests( $args, $assoc_args ) {

		list( $package_dir ) = $args;

		if ( is_file( $package_dir ) ) {
			$package_dir = dirname( $package_dir );
		} else if ( is_dir( $package_dir ) ) {
			$package_dir = rtrim( $package_dir, '/' );
		}

		if ( ! is_dir( $package_dir ) || ! file_exists( $package_dir . '/composer.json' ) ) {
			WP_CLI::error( "Invalid package directory. composer.json file must be present." );
		}

		$package_dir .= '/';
		$bin_dir = $package_dir . 'bin/';
		$utils_dir = $package_dir . 'utils/';
		$features_dir = $package_dir . 'features/';
		$bootstrap_dir = $features_dir . 'bootstrap/';
		$steps_dir = $features_dir . 'steps/';
		$extra_dir = $features_dir . 'extra/';
		foreach( array( $features_dir, $bootstrap_dir, $steps_dir, $extra_dir, $utils_dir, $bin_dir ) as $dir ) {
			if ( ! is_dir( $dir ) ) {
				Process::create( Utils\esc_cmd( 'mkdir %s', $dir ) )->run();
			}
		}

		$to_copy = array(
			'templates/.travis.package.yml' => $package_dir,
			'templates/load-wp-cli.feature' => $features_dir,
			'templates/install-package-tests.sh' => $bin_dir,
			'features/bootstrap/FeatureContext.php' => $bootstrap_dir,
			'features/bootstrap/support.php' => $bootstrap_dir,
			'php/WP_CLI/Process.php' => $bootstrap_dir,
			'php/utils.php' => $bootstrap_dir,
			'utils/get-package-require-from-composer.php' => $utils_dir,
			'features/steps/given.php' => $steps_dir,
			'features/steps/when.php' => $steps_dir,
			'features/steps/then.php' => $steps_dir,
			'features/extra/no-mail.php' => $extra_dir,
		);

		foreach ( $to_copy as $file => $dir ) {
			// file_get_contents() works with Phar-archived files
			$contents = file_get_contents( WP_CLI_ROOT . "/{$file}" );
			$file_path = $dir . basename( $file );
			$file_path = str_replace( array( '.travis.package.yml' ), array( '.travis.yml'), $file_path );
			$result = Process::create( Utils\esc_cmd( 'touch %s', $file_path ) )->run();
			file_put_contents( $file_path, $contents );
			if ( 'templates/install-package-tests.sh' === $file ) {
				Process::create( Utils\esc_cmd( 'chmod +x %s', $file_path ) )->run();
			}
		}

		WP_CLI::success( "Created test files." );

	}

	/**
	 * Generate starter code for a plugin.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The internal name of the plugin.
	 *
	 * [--plugin_name=<title>]
	 * : What to put in the 'Plugin Name:' header
	 *
	 * [--skip-tests]
	 * : Don't generate files for unit testing.
	 *
	 * [--activate]
	 * : Activate the newly generated plugin.
	 */
	function plugin( $args, $assoc_args ) {
		$plugin_slug = $args[0];

		$data = wp_parse_args( $assoc_args, array(
			'plugin_name' => ucfirst( $plugin_slug ),
		) );

		$data['textdomain'] = $plugin_slug;

		$plugin_dir = WP_PLUGIN_DIR . "/$plugin_slug";
		$plugin_path = "$plugin_dir/$plugin_slug.php";
		$plugin_readme_path = "$plugin_dir/readme.txt";

		$this->maybe_create_plugins_dir();

		$this->create_file( $plugin_path, Utils\mustache_render( 'plugin.mustache', $data ) );
		$this->create_file( $plugin_readme_path, Utils\mustache_render( 'plugin-readme.mustache', $data ) );

		WP_CLI::success( "Created $plugin_dir" );

		if ( !isset( $assoc_args['skip-tests'] ) ) {
			WP_CLI::run_command( array( 'scaffold', 'plugin-tests', $plugin_slug ) );
		}

		if ( isset( $assoc_args['activate'] ) ) {
			WP_CLI::run_command( array( 'plugin', 'activate', $plugin_slug ) );
		}
	}

	/**
	 * Generate files needed for running PHPUnit tests.
	 *
	 * ## DESCRIPTION
	 *
	 * These are the files that are generated:
	 *
	 * * `phpunit.xml` is the configuration file for PHPUnit
	 * * `.travis.yml` is the configuration file for Travis CI
	 * * `tests/bootstrap.php` is the file that makes the current plugin active when running the test suite
	 * * `tests/test-sample.php` is a sample file containing the actual tests
	 *
	 * ## ENVIRONMENT
	 *
	 * The `tests/bootstrap.php` file looks for the WP_TESTS_DIR environment
	 * variable.
	 *
	 * ## OPTIONS
	 *
	 * <plugin>
	 * : The name of the plugin to generate test files for.
	 *
	 * ## EXAMPLE
	 *
	 *     wp scaffold plugin-tests hello
	 *
	 * @subcommand plugin-tests
	 */
	function plugin_tests( $args, $assoc_args ) {
		$wp_filesystem = $this->init_wp_filesystem();

		$plugin_slug = $args[0];

		$plugin_dir = WP_PLUGIN_DIR . "/$plugin_slug";
		$tests_dir = "$plugin_dir/tests";
		$bin_dir = "$plugin_dir/bin";

		$wp_filesystem->mkdir( $tests_dir );
		$wp_filesystem->mkdir( $bin_dir );

		$this->create_file( "$tests_dir/bootstrap.php",
			Utils\mustache_render( 'bootstrap.mustache', compact( 'plugin_slug' ) ) );

		$to_copy = array(
			'install-wp-tests.sh' => $bin_dir,
			'.travis.yml' => $plugin_dir,
			'phpunit.xml' => $plugin_dir,
			'test-sample.php' => $tests_dir,
		);

		foreach ( $to_copy as $file => $dir ) {
			$wp_filesystem->copy( WP_CLI_ROOT . "/templates/$file", "$dir/$file", true );
			if ( 'install-wp-tests.sh' === $file ) {
				if ( ! $wp_filesystem->chmod( "$dir/$file", '0755' ) ) {
					WP_CLI::warning( "Couldn't mark install-wp-tests.sh as executable." );
				}
			}
		}

		WP_CLI::success( "Created test files." );
	}

	private function create_file( $filename, $contents ) {
		$wp_filesystem = $this->init_wp_filesystem();

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

	protected function quote_comma_list_elements( $comma_list ) {
		return "'" . implode( "', '", explode( ',', $comma_list ) ) . "'";
	}

	/**
	 * Create the themes directory if it doesn't already exist
	 */
	protected function maybe_create_themes_dir() {

		$themes_dir = WP_CONTENT_DIR . '/themes';
		if ( ! is_dir( $themes_dir ) ) {
			wp_mkdir_p( $themes_dir );
		}

	}

	/**
	 * Create the plugins directory if it doesn't already exist
	 */
	protected function maybe_create_plugins_dir() {

		if ( ! is_dir( WP_PLUGIN_DIR ) ) {
			wp_mkdir_p( WP_PLUGIN_DIR );
		}

	}

	/**
	 * Initialize WP Filesystem
	 */
	private function init_wp_filesystem() {
		global $wp_filesystem;
		WP_Filesystem();
		return $wp_filesystem;
	}

}

WP_CLI::add_command( 'scaffold', 'Scaffold_Command' );

