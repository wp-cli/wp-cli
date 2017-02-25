<?php

use WP_CLI\Utils;
use WP_CLI\Process;

/**
 * Generate code for post types, taxonomies, plugins, child themes. etc.
 *
 * ## EXAMPLES
 *
 *     # Generate a new plugin with unit tests
 *     $ wp scaffold plugin sample-plugin
 *     Success: Created plugin files.
 *     Success: Created test files.
 *
 *     # Generate theme based on _s
 *     $ wp scaffold _s sample-theme --theme_name="Sample Theme" --author="John Doe"
 *     Success: Created theme 'Sample Theme'.
 *
 *     # Generate code for post type registration in given theme
 *     $ wp scaffold post-type movie --label=Movie --theme=simple-life
 *     Success: Created /var/www/example.com/public_html/wp-content/themes/simple-life/post-types/movie.php
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
	 * : The text used to translate the update messages.
	 *
	 * [--textdomain=<textdomain>]
	 * : The textdomain to use for the labels.
	 *
	 * [--dashicon=<dashicon>]
	 * : The dashicon to use in the menu.
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
	 * [--force]
	 * : Overwrite files that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate a 'movie' post type for the 'simple-life' theme
	 *     $ wp scaffold post-type movie --label=Movie --theme=simple-life
	 *     Success: Created '/var/www/example.com/public_html/wp-content/themes/simple-life/post-types/movie.php'.
	 *
	 * @subcommand post-type
	 *
	 * @alias      cpt
	 */
	public function post_type( $args, $assoc_args ) {

		if ( strlen( $args[0] ) > 20 ) {
			WP_CLI::error( "Post type slugs cannot exceed 20 characters in length." );
		}

		$defaults = array(
			'textdomain' => '',
			'dashicon'   => 'admin-post',
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
	 * [--force]
	 * : Overwrite files that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate PHP code for registering a custom taxonomy and save in a file
	 *     $ wp scaffold taxonomy venue --post_types=event,presentation > taxonomy.php
	 *
	 * @subcommand taxonomy
	 *
	 * @alias      tax
	 */
	public function taxonomy( $args, $assoc_args ) {
		$defaults = array(
			'textdomain' => '',
			'post_types' => "'post'"
		);

		if ( isset( $assoc_args['post_types'] ) ) {
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
		$machine_name        = preg_replace( '/-/', '_', $slug );
		$machine_name_plural = $this->pluralize( $slug );

		list( $raw_template, $extended_template ) = $templates;

		$raw_output = Utils\mustache_render( $raw_template, $vars );

		if ( ! $control_args['raw'] ) {
			$vars = array_merge( $vars, array(
				'machine_name' => $machine_name,
				'output'       => $raw_output
			) );

			$final_output = Utils\mustache_render( $extended_template, $vars );
		} else {
			$final_output = $raw_output;
		}

		if ( $path = $this->get_output_path( $control_args, $subdir ) ) {
			$filename = $path . $slug . '.php';

			$force = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' );
			$files_written = $this->create_files( array( $filename => $final_output ), $force );
			$this->log_whether_files_written(
				$files_written,
				$skip_message = "Skipped creating '$filename'.",
				$success_message = "Created '$filename'."
			);

		} else {
			// STDOUT
			echo $final_output;
		}
	}

	/**
	 * Generate starter code for a theme based on _s.
	 *
	 * See the [Underscores website](http://underscores.me/) for more details.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The slug for the new theme, used for prefixing functions.
	 *
	 * [--activate]
	 * : Activate the newly downloaded theme.
	 *
	 * [--enable-network]
	 * : Enable the newly downloaded theme for the entire network.
	 *
	 * [--theme_name=<title>]
	 * : What to put in the 'Theme Name:' header in 'style.css'.
	 *
	 * [--author=<full-name>]
	 * : What to put in the 'Author:' header in 'style.css'.
	 *
	 * [--author_uri=<uri>]
	 * : What to put in the 'Author URI:' header in 'style.css'.
	 *
	 * [--sassify]
	 * : Include stylesheets as SASS.
	 *
	 * [--force]
	 * : Overwrite files that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate a theme with name "Sample Theme" and author "John Doe"
	 *     $ wp scaffold _s sample-theme --theme_name="Sample Theme" --author="John Doe"
	 *     Success: Created theme 'Sample Theme'.
	 */
	public function _s( $args, $assoc_args ) {

		$theme_slug = $args[0];
		$theme_path = WP_CONTENT_DIR . "/themes";
		$url        = "http://underscores.me";
		$timeout    = 30;

		if ( in_array( $theme_slug, array( '.', '..' ) ) ) {
			WP_CLI::error( "Invalid theme slug specified." );
		}

		if ( ! preg_match( '/^[a-z_]\w+$/i', str_replace( '-', '_', $theme_slug ) ) ) {
			WP_CLI::error( "Invalid theme slug specified. Theme slugs can only contain letters, numbers, underscores and hyphens, and can only start with a letter or underscore." );
		}

		$data = wp_parse_args( $assoc_args, array(
			'theme_name' => ucfirst( $theme_slug ),
			'author'     => "Me",
			'author_uri' => "",
		) );

		$_s_theme_path = "$theme_path/$data[theme_name]";

		if ( ! $this->check_target_directory( "theme", $_s_theme_path ) ) {
			WP_CLI::error( "Invalid theme slug specified." );
		}

		$force = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' );
		$should_write_file = $this->prompt_if_files_will_be_overwritten( $_s_theme_path, $force );
		if ( ! $should_write_file ) {
			WP_CLI::log( 'No files created' );
			die;
		}

		$theme_description = "Custom theme: " . $data['theme_name'] . ", developed by " . $data['author'];

		$body                                  = array();
		$body['underscoresme_name']            = $data['theme_name'];
		$body['underscoresme_slug']            = $theme_slug;
		$body['underscoresme_author']          = $data['author'];
		$body['underscoresme_author_uri']      = $data['author_uri'];
		$body['underscoresme_description']     = $theme_description;
		$body['underscoresme_generate_submit'] = "Generate";
		$body['underscoresme_generate']        = "1";
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'sassify' ) ) {
			$body['underscoresme_sass'] = 1;
		}

		$tmpfname = wp_tempnam( $url );
		$response = wp_remote_post( $url, array(
			'timeout'  => $timeout,
			'body'     => $body,
			'stream'   => true,
			'filename' => $tmpfname
		) );

		if ( is_wp_error( $response ) ) {
			WP_CLI::error( $response );
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 != $response_code ) {
			WP_CLI::error( "Couldn't create theme (received $response_code response)." );
		}

		$this->maybe_create_themes_dir();

		$this->init_wp_filesystem();

		$unzip_result = unzip_file( $tmpfname, $theme_path );
		unlink( $tmpfname );

		if ( true === $unzip_result ) {
			WP_CLI::success( "Created theme '{$data['theme_name']}'." );
		} else {
			WP_CLI::error( "Could not decompress your theme files ('{$tmpfname}') at '{$theme_path}': {$unzip_result->get_error_message()}" );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'activate' ) ) {
			WP_CLI::run_command( array( 'theme', 'activate', $theme_slug ) );
		} else if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'enable-network' ) ) {
			WP_CLI::run_command( array( 'theme', 'enable', $theme_slug ), array( 'network' => true ) );
		}
	}

	/**
	 * Generate child theme based on an existing theme.
	 *
	 * Creates a child theme folder with `functions.php` and `style.css` files.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The slug for the new child theme.
	 *
	 * --parent_theme=<slug>
	 * : What to put in the 'Template:' header in 'style.css'.
	 *
	 * [--theme_name=<title>]
	 * : What to put in the 'Theme Name:' header in 'style.css'.
	 *
	 * [--author=<full-name>]
	 * : What to put in the 'Author:' header in 'style.css'.
	 *
	 * [--author_uri=<uri>]
	 * : What to put in the 'Author URI:' header in 'style.css'.
	 *
	 * [--theme_uri=<uri>]
	 * : What to put in the 'Theme URI:' header in 'style.css'.
	 *
	 * [--activate]
	 * : Activate the newly created child theme.
	 *
	 * [--enable-network]
	 * : Enable the newly created child theme for the entire network.
	 *
	 * [--force]
	 * : Overwrite files that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate a 'sample-theme' child theme based on TwentySixteen
	 *     $ wp scaffold child-theme sample-theme --parent_theme=twentysixteen
	 *     Success: Created '/var/www/example.com/public_html/wp-content/themes/sample-theme'.
	 *
	 * @subcommand child-theme
	 */
	function child_theme( $args, $assoc_args ) {
		$theme_slug = $args[0];

		if ( in_array( $theme_slug, array( '.', '..' ) ) ) {
			WP_CLI::error( "Invalid theme slug specified." );
		}

		$data = wp_parse_args( $assoc_args, array(
			'theme_name' => ucfirst( $theme_slug ),
			'author'     => "Me",
			'author_uri' => "",
			'theme_uri'  => ""
		) );
		$data['slug'] = $theme_slug;
		$data['parent_theme_function_safe'] = str_replace( '-', '_', $data['parent_theme'] );

		$data['description'] = ucfirst( $data['parent_theme'] ) . " child theme.";

		$theme_dir = WP_CONTENT_DIR . "/themes" . "/$theme_slug";

		if ( ! $this->check_target_directory( "theme", $theme_dir ) ) {
			WP_CLI::error( "Invalid theme slug specified." );
		}

		$theme_style_path     = "$theme_dir/style.css";
		$theme_functions_path = "$theme_dir/functions.php";

		$this->maybe_create_themes_dir();

		$force = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' );
		$files_written = $this->create_files( array(
			$theme_style_path => Utils\mustache_render( 'child_theme.mustache', $data ),
			$theme_functions_path => Utils\mustache_render( 'child_theme_functions.mustache', $data )
		), $force );
		$this->log_whether_files_written(
			$files_written,
			$skip_message = 'All theme files were skipped.',
			$success_message = "Created '$theme_dir'."
		);

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'activate' ) ) {
			WP_CLI::run_command( array( 'theme', 'activate', $theme_slug ) );
		} else if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'enable-network' ) ) {
			WP_CLI::run_command( array( 'theme', 'enable', $theme_slug ), array( 'network' => true ) );
		}
	}

	private function get_output_path( $assoc_args, $subdir ) {
		if ( $assoc_args['theme'] ) {
			$theme = $assoc_args['theme'];
			if ( is_string( $theme ) ) {
				$path = get_theme_root( $theme ) . '/' . $theme;
			} else {
				$path = get_stylesheet_directory();
			}
		} elseif ( $assoc_args['plugin'] ) {
			$plugin = $assoc_args['plugin'];
			$path   = WP_PLUGIN_DIR . '/' . $plugin;
			if ( ! is_dir( $path ) ) {
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
	 * The following files are always generated:
	 *
	 * * `plugin-slug.php` is the main PHP plugin file.
	 * * `readme.txt` is the readme file for the plugin.
	 * * `package.json` needed by NPM holds various metadata relevant to the project. Packages: `grunt`, `grunt-wp-i18n` and `grunt-wp-readme-to-markdown`.
	 * * `Gruntfile.js` is the JS file containing Grunt tasks. Tasks: `i18n` containing `addtextdomain` and `makepot`, `readme` containing `wp_readme_to_markdown`.
	 * * `.editorconfig` is the configuration file for Editor.
	 * * `.gitignore` tells which files (or patterns) git should ignore.
	 * * `.distignore` tells which files and folders should be ignored in distribution.
	 *
	 * The following files are also included unless the `--skip-tests` is used:
	 *
	 * * `phpunit.xml.dist` is the configuration file for PHPUnit.
	 * * `.travis.yml` is the configuration file for Travis CI. Use `--ci=<provider>` to select a different service.
	 * * `bin/install-wp-tests.sh` configures the WordPress test suite and a test database.
	 * * `tests/bootstrap.php` is the file that makes the current plugin active when running the test suite.
	 * * `tests/test-sample.php` is a sample file containing test cases.
	 * * `phpcs.ruleset.xml` is a collenction of PHP_CodeSniffer rules.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : The internal name of the plugin.
	 *
	 * [--dir=<dirname>]
	 * : Put the new plugin in some arbitrary directory path. Plugin directory will be path plus supplied slug.
	 *
	 * [--plugin_name=<title>]
	 * : What to put in the 'Plugin Name:' header.
	 *
	 * [--plugin_description=<description>]
	 * : What to put in the 'Description:' header.
	 *
	 * [--plugin_author=<author>]
	 * : What to put in the 'Author:' header.
	 *
	 * [--plugin_author_uri=<url>]
	 * : What to put in the 'Author URI:' header.
	 *
	 * [--plugin_uri=<url>]
	 * : What to put in the 'Plugin URI:' header.
	 *
	 * [--skip-tests]
	 * : Don't generate files for unit testing.
	 *
	 * [--ci=<provider>]
	 * : Choose a configuration file for a continuous integration provider.
	 * ---
	 * default: travis
	 * options:
	 *   - travis
	 *   - circle
	 *   - gitlab
	 * ---
	 *
	 * [--activate]
	 * : Activate the newly generated plugin.
	 *
	 * [--activate-network]
	 * : Network activate the newly generated plugin.
	 *
	 * [--force]
	 * : Overwrite files that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp scaffold plugin sample-plugin
	 *     Success: Created plugin files.
	 *     Success: Created test files.
	 */
	function plugin( $args, $assoc_args ) {
		$plugin_slug    = $args[0];
		$plugin_name    = ucwords( str_replace( '-', ' ', $plugin_slug ) );
		$plugin_package = str_replace( ' ', '_', $plugin_name );

		if ( in_array( $plugin_slug, array( '.', '..' ) ) ) {
			WP_CLI::error( "Invalid plugin slug specified." );
		}

		$data = wp_parse_args( $assoc_args, array(
			'plugin_slug'         => $plugin_slug,
			'plugin_name'         => $plugin_name,
			'plugin_package'      => $plugin_package,
			'plugin_description'  => 'PLUGIN DESCRIPTION HERE',
			'plugin_author'       => 'YOUR NAME HERE',
			'plugin_author_uri'   => 'YOUR SITE HERE',
			'plugin_uri'          => 'PLUGIN SITE HERE',
			'plugin_tested_up_to' => get_bloginfo('version'),
		) );

		$data['textdomain'] = $plugin_slug;

		if ( ! empty( $assoc_args['dir'] ) ) {
			if ( ! is_dir( $assoc_args['dir'] ) ) {
				WP_CLI::error( "Cannot create plugin in directory that doesn't exist." );
			}
			$plugin_dir = $assoc_args['dir'] . "/$plugin_slug";
		} else {
			$plugin_dir = WP_PLUGIN_DIR . "/$plugin_slug";
			$this->maybe_create_plugins_dir();

			if ( ! $this->check_target_directory( "plugin", $plugin_dir ) ) {
				WP_CLI::error( "Invalid plugin slug specified." );
			}
		}

		$plugin_path = "$plugin_dir/$plugin_slug.php";
		$plugin_readme_path = "$plugin_dir/readme.txt";

		$force = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' );
		$files_written = $this->create_files( array(
			$plugin_path => Utils\mustache_render( 'plugin.mustache', $data ),
			$plugin_readme_path => Utils\mustache_render( 'plugin-readme.mustache', $data ),
			"$plugin_dir/package.json" => Utils\mustache_render( 'plugin-packages.mustache', $data ),
			"$plugin_dir/Gruntfile.js" => Utils\mustache_render( 'plugin-gruntfile.mustache', $data ),
			"$plugin_dir/.gitignore" => Utils\mustache_render( 'plugin-gitignore.mustache', $data ),
			"$plugin_dir/.distignore" => Utils\mustache_render( 'plugin-distignore.mustache', $data ),
			"$plugin_dir/.editorconfig" => file_get_contents( WP_CLI_ROOT . "/templates/.editorconfig" ),
		), $force );

		$this->log_whether_files_written(
			$files_written,
			$skip_message = 'All plugin files were skipped.',
			$success_message = 'Created plugin files.'
		);

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-tests' ) ) {
			WP_CLI::run_command( array( 'scaffold', 'plugin-tests', $plugin_slug ), array( 'dir' => $plugin_dir, 'ci' => $assoc_args['ci'], 'force' => $force ) );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'activate' ) ) {
			WP_CLI::run_command( array( 'plugin', 'activate', $plugin_slug ) );
		} else if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'activate-network' ) ) {
			WP_CLI::run_command( array( 'plugin', 'activate', $plugin_slug), array( 'network' => true ) );
		}
	}

	/**
	 * Generate files needed for running PHPUnit tests in a plugin.
	 *
	 * The following files are generated by default:
	 *
	 * * `phpunit.xml.dist` is the configuration file for PHPUnit.
	 * * `.travis.yml` is the configuration file for Travis CI. Use `--ci=<provider>` to select a different service.
	 * * `bin/install-wp-tests.sh` configures the WordPress test suite and a test database.
	 * * `tests/bootstrap.php` is the file that makes the current plugin active when running the test suite.
	 * * `tests/test-sample.php` is a sample file containing the actual tests.
	 * * `phpcs.ruleset.xml` is a collenction of PHP_CodeSniffer rules.
	 *
	 * Learn more from the [plugin unit tests documentation](http://wp-cli.org/docs/plugin-unit-tests/).
	 *
	 * ## ENVIRONMENT
	 *
	 * The `tests/bootstrap.php` file looks for the WP_TESTS_DIR environment
	 * variable.
	 *
	 * ## OPTIONS
	 *
	 * [<plugin>]
	 * : The name of the plugin to generate test files for.
	 *
	 * [--dir=<dirname>]
	 * : Generate test files for a non-standard plugin path. If no plugin slug is specified, the directory name is used.
	 *
	 * [--ci=<provider>]
	 * : Choose a configuration file for a continuous integration provider.
	 * ---
	 * default: travis
	 * options:
	 *   - travis
	 *   - circle
	 *	 - gitlab
	 * ---
	 *
	 * [--force]
	 * : Overwrite files that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate unit test files for plugin 'sample-plugin'.
	 *     $ wp scaffold plugin-tests sample-plugin
	 *     Success: Created test files.
	 *
	 * @subcommand plugin-tests
	 */
	public function plugin_tests( $args, $assoc_args ) {
		$this->scaffold_plugin_theme_tests( $args, $assoc_args, 'plugin' );
	}

	/**
	 * Generate files needed for running PHPUnit tests in a theme.
	 *
	 * The following files are generated by default:
	 *
	 * * `phpunit.xml.dist` is the configuration file for PHPUnit.
	 * * `.travis.yml` is the configuration file for Travis CI. Use `--ci=<provider>` to select a different service.
	 * * `bin/install-wp-tests.sh` configures the WordPress test suite and a test database.
	 * * `tests/bootstrap.php` is the file that makes the current theme active when running the test suite.
	 * * `tests/test-sample.php` is a sample file containing the actual tests.
	 * * `phpcs.ruleset.xml` is a collenction of PHP_CodeSniffer rules.
	 *
	 * Learn more from the [plugin unit tests documentation](http://wp-cli.org/docs/plugin-unit-tests/).
	 *
	 * ## ENVIRONMENT
	 *
	 * The `tests/bootstrap.php` file looks for the WP_TESTS_DIR environment
	 * variable.
	 *
	 * ## OPTIONS
	 *
	 * [<theme>]
	 * : The name of the theme to generate test files for.
	 *
	 * [--dir=<dirname>]
	 * : Generate test files for a non-standard theme path. If no theme slug is specified, the directory name is used.
	 *
	 * [--ci=<provider>]
	 * : Choose a configuration file for a continuous integration provider.
	 * ---
	 * default: travis
	 * options:
	 *   - travis
	 *   - circle
	 *	 - gitlab
	 * ---
	 *
	 * [--force]
	 * : Overwrite files that already exist.
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate unit test files for theme 'twentysixteenchild'.
	 *     $ wp scaffold theme-tests twentysixteenchild
	 *     Success: Created test files.
	 *
	 * @subcommand theme-tests
	 */
	public function theme_tests( $args, $assoc_args ) {
		$this->scaffold_plugin_theme_tests( $args, $assoc_args, 'theme' );
	}

	private function scaffold_plugin_theme_tests( $args, $assoc_args, $type ) {
		$wp_filesystem = $this->init_wp_filesystem();

		if ( ! empty( $args[0] ) ) {
			$slug = $args[0];
			if ( in_array( $slug, array( '.', '..' ) ) ) {
				WP_CLI::error( "Invalid {$type} slug specified." );
			}
			if ( 'theme' === $type ) {
				$theme = wp_get_theme( $slug );
				if ( $theme->exists() ) {
					$target_dir = $theme->get_stylesheet_directory();
				} else {
					WP_CLI::error( "Invalid {$type} slug specified." );
				}
			} else {
				$target_dir = WP_PLUGIN_DIR . "/$slug";
			}
			if ( empty( $assoc_args['dir'] ) && ! is_dir( $target_dir ) ) {
				WP_CLI::error( "Invalid {$type} slug specified." );
			}
			if ( ! $this->check_target_directory( $type, $target_dir ) ) {
				WP_CLI::error( "Invalid {$type} slug specified." );
			}
		}

		if ( ! empty( $assoc_args['dir'] ) ) {
			$target_dir = $assoc_args['dir'];
			if ( ! is_dir( $target_dir ) ) {
				WP_CLI::error( "Invalid {$type} directory specified." );
			}
			if ( empty( $slug ) ) {
				$slug = basename( $target_dir );
			}
		}

		if ( empty( $slug ) || empty( $target_dir ) ) {
			WP_CLI::error( "Invalid {$type} specified." );
		}

		$name    = ucwords( str_replace( '-', ' ', $slug ) );
		$package = str_replace( ' ', '_', $name );

		$tests_dir = "{$target_dir}/tests";
		$bin_dir = "{$target_dir}/bin";

		$wp_filesystem->mkdir( $tests_dir );
		$wp_filesystem->mkdir( $bin_dir );

		$wp_versions_to_test = array('latest');
		// Parse plugin readme.txt
		if ( file_exists( $target_dir . '/readme.txt' ) ) {
			$readme_content = file_get_contents( $target_dir . '/readme.txt' );

			preg_match( '/Requires at least\:(.*)\n/m', $readme_content, $matches );
			if ( isset( $matches[1] ) && $matches[1] ) {
				$wp_versions_to_test[] = trim( $matches[1] );
			}
			preg_match( '/Tested up to\:(.*)\n/m', $readme_content, $matches );
			if ( isset( $matches[1] ) && $matches[1] ) {
				$wp_versions_to_test[] = trim( $matches[1] );
			}
		}

		$template_data = array(
			"{$type}_slug"    => $slug,
			"{$type}_package" => $package,
		);

		$force = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' );
		$files_to_create = array(
			"$tests_dir/bootstrap.php"   => Utils\mustache_render( "{$type}-bootstrap.mustache", $template_data ),
			"$tests_dir/test-sample.php" => Utils\mustache_render( "{$type}-test-sample.mustache", $template_data ),
		);
		if ( 'travis' === $assoc_args['ci'] ) {
			$files_to_create["{$target_dir}/.travis.yml"] = Utils\mustache_render( 'plugin-travis.mustache', compact( 'wp_versions_to_test' ) );
		} else if ( 'circle' === $assoc_args['ci'] ) {
			$files_to_create["{$target_dir}/circle.yml"] = Utils\mustache_render( 'plugin-circle.mustache' );
		} else if ( 'gitlab' === $assoc_args['ci'] ) {
			$files_to_create["{$target_dir}/.gitlab-ci.yml"] = Utils\mustache_render( 'plugin-gitlab.mustache' );
		}
		$files_written = $this->create_files( $files_to_create, $force );

		$to_copy = array(
			'install-wp-tests.sh' => $bin_dir,
			'phpunit.xml.dist'    => $target_dir,
			'phpcs.ruleset.xml'   => $target_dir,
		);

		foreach ( $to_copy as $file => $dir ) {
			$file_name = "$dir/$file";
			$force = \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' );
			$should_write_file = $this->prompt_if_files_will_be_overwritten( $file_name, $force );
			if ( ! $should_write_file ) {
				continue;
			}
			$files_written[] = $file_name;

			$wp_filesystem->copy( WP_CLI_ROOT . "/templates/$file", $file_name, true );
			if ( 'install-wp-tests.sh' === $file ) {
				if ( ! $wp_filesystem->chmod( "$dir/$file", 0755 ) ) {
					WP_CLI::warning( "Couldn't mark 'install-wp-tests.sh' as executable." );
				}
			}
		}
		$this->log_whether_files_written(
			$files_written,
			$skip_message = 'All test files were skipped.',
			$success_message = 'Created test files.'
		);
	}

	private function check_target_directory( $type, $target_dir ) {
		if ( realpath( $target_dir ) ) {
			$target_dir = realpath( $target_dir );
		}

		$parent_dir = dirname( $target_dir );

		if ( "theme" === $type ) {
			if ( WP_CONTENT_DIR . '/themes' === $parent_dir ) {
				return true;
			}
		} elseif ( "plugin" === $type ) {
			if ( WP_PLUGIN_DIR === $parent_dir ) {
				return true;
			}
		}

		return false;
	}

	private function create_files( $files_and_contents, $force ) {
		$wp_filesystem = $this->init_wp_filesystem();
		$wrote_files = array();

		foreach ( $files_and_contents as $filename => $contents ) {
			$should_write_file = $this->prompt_if_files_will_be_overwritten( $filename, $force );
			if ( ! $should_write_file ) {
				continue;
			}

			$wp_filesystem->mkdir( dirname( $filename ) );

			if ( ! $wp_filesystem->put_contents( $filename, $contents ) ) {
				WP_CLI::error( "Error creating file: $filename" );
			} elseif ( $should_write_file ) {
				$wrote_files[] = $filename;
			}
		}
		return $wrote_files;
	}

	private function prompt_if_files_will_be_overwritten( $filename, $force ) {
		$should_write_file = true;
		if ( ! file_exists( $filename ) ) {
			return true;
		}

		WP_CLI::warning( 'File already exists.' );
		WP_CLI::log( $filename );
		if ( ! $force ) {
			do {
				$answer = cli\prompt(
					'Skip this file, or replace it with scaffolding?',
					$default = false,
					$marker = '[s/r]: '
				);
			} while ( ! in_array( $answer, array( 's', 'r' ) ) );
			$should_write_file = 'r' === $answer;
		}

		$outcome = $should_write_file ? 'Replacing' : 'Skipping';
		WP_CLI::log( $outcome . PHP_EOL );

		return $should_write_file;
	}

	private function log_whether_files_written( $files_written, $skip_message, $success_message ) {
		if ( empty( $files_written ) ) {
			WP_CLI::log( $skip_message );
		} else {
			WP_CLI::success( $success_message );
		}
	}

	/**
	 * If you're writing your files to your theme directory your textdomain also needs to be the same as your theme.
	 * Same goes for when plugin is being used.
	 */
	private function get_textdomain( $textdomain, $args ) {
		if ( strlen( $textdomain ) ) {
			return $textdomain;
		}

		if ( $args['theme'] ) {
			return strtolower( wp_get_theme()->template );
		}

		if ( $args['plugin'] && true !== $args['plugin'] ) {
			return $args['plugin'];
		}

		return 'YOUR-TEXTDOMAIN';
	}

	private function pluralize( $word ) {
		$plural = array(
			'/(quiz)$/i'               => '\1zes',
			'/^(ox)$/i'                => '\1en',
			'/([m|l])ouse$/i'          => '\1ice',
			'/(matr|vert|ind)ix|ex$/i' => '\1ices',
			'/(x|ch|ss|sh)$/i'         => '\1es',
			'/([^aeiouy]|qu)ies$/i'    => '\1y',
			'/([^aeiouy]|qu)y$/i'      => '\1ies',
			'/(hive)$/i'               => '\1s',
			'/(?:([^f])fe|([lr])f)$/i' => '\1\2ves',
			'/sis$/i'                  => 'ses',
			'/([ti])um$/i'             => '\1a',
			'/(buffal|tomat)o$/i'      => '\1oes',
			'/(bu)s$/i'                => '1ses',
			'/(alias|status)/i'        => '\1es',
			'/(octop|vir)us$/i'        => '1i',
			'/(ax|test)is$/i'          => '\1es',
			'/s$/i'                    => 's',
			'/$/'                      => 's'
		);

		$uncountable = array( 'equipment', 'information', 'rice', 'money', 'species', 'series', 'fish', 'sheep' );

		$irregular = array(
			'person' => 'people',
			'man'    => 'men',
			'woman'  => 'women',
			'child'  => 'children',
			'sex'    => 'sexes',
			'move'   => 'moves'
		);

		$lowercased_word = strtolower( $word );

		foreach ( $uncountable as $_uncountable ) {
			if ( substr( $lowercased_word, ( - 1 * strlen( $_uncountable ) ) ) == $_uncountable ) {
				return $word;
			}
		}

		foreach ( $irregular as $_plural => $_singular ) {
			if ( preg_match( '/(' . $_plural . ')$/i', $word, $arr ) ) {
				return preg_replace( '/(' . $_plural . ')$/i', substr( $arr[0], 0, 1 ) . substr( $_singular, 1 ), $word );
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
			$out[ $key ] = \WP_CLI\Utils\get_flag_value( $assoc_args, $key, $value );
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
