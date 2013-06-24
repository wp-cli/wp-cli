<?php

use Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

function invoke_proc( $proc, $mode, $subdir = null ) {
	$map = array(
		'run' => 'run_check',
		'try' => 'run'
	);
	$method = $map[ $mode ];

	return $proc->$method( $subdir );
}

$steps->Given( '/^an empty directory$/',
	function ( $world ) {
		$world->create_empty_dir();
	}
);

$steps->Given( '/^a ([^\s]+) file:$/',
	function ( $world, $path, PyStringNode $content ) {
		$content = (string) $content . "\n";
		$full_path = $world->get_path( $path );
		Process::create( \WP_CLI\utils\esc_cmd( 'mkdir -p %s', dirname( $full_path ) ) )->run_check();
		file_put_contents( $full_path, $content );
	}
);

$steps->Given( '/^WP files$/',
	function ( $world ) {
		$world->download_wordpress_files();
	}
);

$steps->Given( '/^wp-config\.php$/',
	function ( $world ) {
		$world->proc( 'wp core config' )->run_check();
	}
);

$steps->Given( '/^a database$/',
	function ( $world ) {
		$world->create_db();
	}
);

$steps->Given( '/^a WP install$/',
	function ( $world ) {
		$world->wp_install();
	}
);

$steps->Given( "/^a WP install in '([^\s]+)'$/",
	function ( $world, $subdir ) {
		$world->wp_install( $subdir );
	}
);

$steps->Given( '/^a WP multisite install$/',
	function ( $world ) {
		$world->wp_install();
		$world->proc( 'wp core install-network' )->run_check();
	}
);

$steps->Given( '/^a custom wp-content directory$/',
	function ( $world ) {
		$wp_config_path = $world->get_path( 'wp-config.php' );

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

$steps->Given( '/^a large image file$/',
	function ( $world ) {
		$image_file = 'http://wordpresswallpaper.com/wp-content/gallery/photo-based-wallpaper/1058.jpg';

		$world->variables['DOWNLOADED_IMAGE'] = $world->get_cache_path( 'wallpaper.jpg' );

		$world->download_file( $image_file, $world->variables['DOWNLOADED_IMAGE'] );
	}
);

$steps->When( '/^I (run|try) `([^`]+)`$/',
	function ( $world, $mode, $cmd ) {
		$cmd = $world->replace_variables( $cmd );
		$world->result = invoke_proc( $world->proc( $cmd ), $mode );
	}
);

$steps->When( "/^I (run|try) `([^`]+)` from '([^\s]+)'$/",
	function ( $world, $mode, $cmd, $subdir ) {
		$cmd = $world->replace_variables( $cmd );
		$world->result = invoke_proc( $world->proc( $cmd ), $mode, $subdir );
	}
);

$steps->When( '/^I (run|try) the previous command again$/',
	function ( $world, $mode ) {
		if ( !isset( $world->result ) )
			throw new \Exception( 'No previous command.' );

		$proc = Process::create( $world->result->command, $world->result->cwd );
		$world->result = invoke_proc( $proc, $mode );
	}
);

$steps->When( '/^I try to import it$/',
	function ( $world ) {
		if ( !isset( $world->variables['DOWNLOADED_IMAGE'] ) )
			throw new \Exception( 'Cached image not available.' );

		$world->result = $world->proc( 'wp media import ' . $world->variables['DOWNLOADED_IMAGE'] . ' --post_id=1 --featured_image' )->run();
	}
);

$steps->Given( '/^save (STDOUT|STDERR) ([\'].+[^\'])?as \{(\w+)\}$/',
	function ( $world, $stream, $output_filter, $key ) {
	
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

$steps->Then( '/^the return code should be (\d+)$/',
	function ( $world, $return_code ) {
		assertEquals( $return_code, $world->result->return_code );
	}
);

$steps->Then( '/^(STDOUT|STDERR) should (be|contain|not contain):$/',
	function ( $world, $stream, $action, PyStringNode $expected ) {
		$expected = $world->replace_variables( (string) $expected );

		checkString( $world->result->$stream, $expected, $action );
	}
);

$steps->Then( '/^(STDOUT|STDERR) should match \'([^\']+)\'$/',
	function ( $world, $stream, $format ) {
		assertStringMatchesFormat( $format, $world->result->$stream );
	}
);

$steps->Then( '/^STDOUT should be a table containing rows:$/',
	function ( $world, TableNode $expected ) {
		$output     = $world->result->STDOUT;
		$outputRows = explode( "\n", rtrim( $output, "\n" ) );

		$expectedRows = array();
		foreach ( $expected->getRows() as $row ) {
			$expectedRows[] = $world->replace_variables( implode( "\t", $row ) );
		}

		// the first row is the header and must be present
		if ( $expectedRows[0] != $outputRows[0] ) {
			throw new \Exception( $output );
		}

		unset($outputRows[0]);
		unset($expectedRows[0]);
		$matches = array_intersect( $expectedRows, $outputRows );
		if ( count( $expectedRows ) != count( $matches ) ) {
			throw new \Exception( $output );
		}
	}
);

$steps->Then( '/^STDOUT should be JSON containing:$/',
	function ( $world, PyStringNode $expected ) {
		$output = $world->result->STDOUT;
		$expected = $world->replace_variables( (string) $expected );

		if ( !checkThatJsonStringContainsJsonString( $output, $expected ) ) {
			throw new \Exception( $output );
		}
});

$steps->Then( '/^STDOUT should be CSV containing:$/',
	function( $world, TableNode $expected ) {
		$output = $world->result->STDOUT;

		$expectedRows = $expected->getRows();
		foreach ( $expected as &$row ) {
			foreach ( $row as &$value ) {
				$value = $world->replace_variables( $value );
			}
		}

		if ( ! checkThatCsvStringContainsValues( $output, $expectedRows ) )
			throw new \Exception( $output );
	}
);

$steps->Then( '/^(STDOUT|STDERR) should be empty$/',
	function ( $world, $stream ) {
		if ( !empty( $world->result->$stream ) ) {
			throw new \Exception( $world->result->$stream );
		}
	}
);

$steps->Then( '/^(STDOUT|STDERR) should not be empty$/',
	function ( $world, $stream ) {
		assertNotEmpty( rtrim( $world->result->$stream, "\n" ) );
	}
);

$steps->Then( '/^the (.+) file should (exist|not exist|be:|contain:|not contain:)$/',
	function ( $world, $path, $action, $expected = null ) {
		$path = $world->replace_variables( $path );

		// If it's a relative path, make it relative to the current test dir
		if ( '/' !== $path[0] )
			$path = $world->get_path( $path );

		switch ( $action ) {
		case 'exist':
			assertFileExists( $path );
			break;
		case 'not exist':
			assertFileNotExists( $path );
			break;
		default:
			assertFileExists( $path );
			$action = substr( $action, 0, -1 );
			$expected = $world->replace_variables( (string) $expected );
			checkString( file_get_contents( $path ), $expected, $action );
		}
	}
);

