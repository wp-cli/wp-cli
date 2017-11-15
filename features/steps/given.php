<?php

use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode,
    WP_CLI\Process;

$steps->Given( '/^an empty directory$/',
	function ( $world ) {
		$world->create_run_dir();
	}
);

$steps->Given( '/^an? (empty|non-existent) ([^\s]+) directory$/',
	function ( $world, $empty_or_nonexistent, $dir ) {
		$dir = $world->replace_variables( $dir );
		if ( ! WP_CLI\Utils\is_path_absolute( $dir ) ) {
			$dir = $world->variables['RUN_DIR'] . "/$dir";
		}
		if ( 0 !== strpos( $dir, sys_get_temp_dir() ) ) {
			throw new RuntimeException( sprintf( "Attempted to delete directory '%s' that is not in the temp directory '%s'. " . __FILE__ . ':' . __LINE__, $dir, sys_get_temp_dir() ) );
		}
		$world->remove_dir( $dir );
		if ( 'empty' === $empty_or_nonexistent ) {
			mkdir( $dir, 0777, true /*recursive*/ );
		}
	}
);

$steps->Given( '/^an empty cache/',
	function ( $world ) {
		$world->variables['SUITE_CACHE_DIR'] = FeatureContext::create_cache_dir();
	}
);

$steps->Given( '/^an? ([^\s]+) file:$/',
	function ( $world, $path, PyStringNode $content ) {
		$content = (string) $content . "\n";
		$full_path = $world->variables['RUN_DIR'] . "/$path";
		$dir = dirname( $full_path );
		if ( ! file_exists( $dir ) ) {
			mkdir( $dir, 0777, true /*recursive*/ );
		}
		file_put_contents( $full_path, $content );
	}
);

$steps->Given( '/^"([^"]+)" replaced with "([^"]+)" in the ([^\s]+) file$/', function( $world, $search, $replace, $path ) {
	$full_path = $world->variables['RUN_DIR'] . "/$path";
	$contents = file_get_contents( $full_path );
	$contents = str_replace( $search, $replace, $contents );
	file_put_contents( $full_path, $contents );
});

$steps->Given( '/^WP files$/',
	function ( $world ) {
		$world->download_wp();
	}
);

$steps->Given( '/^wp-config\.php$/',
	function ( $world ) {
		$world->create_config();
	}
);

$steps->Given( '/^a database$/',
	function ( $world ) {
		$world->create_db();
	}
);

$steps->Given( '/^a WP install$/',
	function ( $world ) {
		$world->install_wp();
	}
);

$steps->Given( "/^a WP install in '([^\s]+)'$/",
	function ( $world, $subdir ) {
		$world->install_wp( $subdir );
	}
);

$steps->Given( '/^a WP install with Composer$/',
	function ( $world ) {
		$world->install_wp_with_composer();
	}
);

$steps->Given( "/^a WP install with Composer and a custom vendor directory '([^\s]+)'$/",
	function ( $world, $vendor_directory ) {
		$world->install_wp_with_composer( $vendor_directory );
	}
);

$steps->Given( '/^a WP multisite (subdirectory|subdomain)?\s?install$/',
	function ( $world, $type = 'subdirectory' ) {
		$world->install_wp();
		$subdomains = ! empty( $type ) && 'subdomain' === $type ? 1 : 0;
		$world->proc( 'wp core install-network', array( 'title' => 'WP CLI Network', 'subdomains' => $subdomains ) )->run_check();
	}
);

$steps->Given( '/^these installed and active plugins:$/',
	function( $world, $stream ) {
		$plugins = implode( ' ', array_map( 'trim', explode( PHP_EOL, (string)$stream ) ) );
		$world->proc( "wp plugin install $plugins --activate" )->run_check();
	}
);

$steps->Given( '/^a custom wp-content directory$/',
	function ( $world ) {
		$wp_config_path = $world->variables['RUN_DIR'] . "/wp-config.php";

		$wp_config_code = file_get_contents( $wp_config_path );

		$world->move_files( 'wp-content', 'my-content' );
		$world->add_line_to_wp_config( $wp_config_code,
			"define( 'WP_CONTENT_DIR', dirname(__FILE__) . '/my-content' );" );

		$world->move_files( 'my-content/plugins', 'my-plugins' );
		$world->add_line_to_wp_config( $wp_config_code,
			"define( 'WP_PLUGIN_DIR', __DIR__ . '/my-plugins' );" );

		file_put_contents( $wp_config_path, $wp_config_code );
	}
);

$steps->Given( '/^download:$/',
	function ( $world, TableNode $table ) {
		foreach ( $table->getHash() as $row ) {
			$path = $world->replace_variables( $row['path'] );
			if ( file_exists( $path ) ) {
				// assume it's the same file and skip re-download
				continue;
			}

			Process::create( \WP_CLI\Utils\esc_cmd( 'curl -sSL %s > %s', $row['url'], $path ) )->run_check();
		}
	}
);

$steps->Given( '/^save (STDOUT|STDERR) ([\'].+[^\'])?\s?as \{(\w+)\}$/',
	function ( $world, $stream, $output_filter, $key ) {

		$stream = strtolower( $stream );

		if ( $output_filter ) {
			$output_filter = '/' . trim( str_replace( '%s', '(.+[^\b])', $output_filter ), "' " ) . '/';
			if ( false !== preg_match( $output_filter, $world->result->$stream, $matches ) )
				$output = array_pop( $matches );
			else
				$output = '';
		} else {
			$output = $world->result->$stream;
		}
		$world->variables[ $key ] = trim( $output, "\n" );
	}
);

$steps->Given( '/^a new Phar with (?:the same version|version "([^"]+)")$/',
	function ( $world, $version = 'same' ) {
		$world->build_phar( $version );
	}
);

$steps->Given( '/^a downloaded Phar with (?:the same version|version "([^"]+)")$/',
	function ( $world, $version = 'same' ) {
		$world->download_phar( $version );
	}
);

$steps->Given( '/^save the (.+) file ([\'].+[^\'])?as \{(\w+)\}$/',
	function ( $world, $filepath, $output_filter, $key ) {
		$full_file = file_get_contents( $world->replace_variables( $filepath ) );

		if ( $output_filter ) {
			$output_filter = '/' . trim( str_replace( '%s', '(.+[^\b])', $output_filter ), "' " ) . '/';
			if ( false !== preg_match( $output_filter, $full_file, $matches ) )
				$output = array_pop( $matches );
			else
				$output = '';
		} else {
			$output = $full_file;
		}
		$world->variables[ $key ] = trim( $output, "\n" );
	}
);

$steps->Given('/^a misconfigured WP_CONTENT_DIR constant directory$/',
	function($world) {
		$wp_config_path = $world->variables['RUN_DIR'] . "/wp-config.php";

		$wp_config_code = file_get_contents( $wp_config_path );

		$world->add_line_to_wp_config( $wp_config_code,
			"define( 'WP_CONTENT_DIR', '' );" );

		file_put_contents( $wp_config_path, $wp_config_code );
	}
);

$steps->Given( '/^a dependency on current wp-cli$/',
	function ( $world ) {
		$world->composer_require_current_wp_cli();
	}
);

$steps->Given( '/^a PHP built-in web server$/',
	function ( $world ) {
		$world->start_php_server();
	}
);

$steps->Given( "/^a PHP built-in web server to serve '([^\s]+)'$/",
	function ( $world, $subdir ) {
		$world->start_php_server( $subdir );
	}
);
