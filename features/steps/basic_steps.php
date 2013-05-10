<?php

use Behat\Behat\Exception\PendingException,
    Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

$steps->Given( '/^an empty directory$/',
	function ( $world ) {
		$world->create_empty_dir();
	}
);

$steps->Given( '/^a ([^\s]+) file:$/',
	function ( $world, $path, PyStringNode $content ) {
		$content = (string) $content . "\n";
		file_put_contents( $world->get_path( $path ), $content );
	}
);

$steps->Given( '/^WP files$/',
	function ( $world ) {
		$world->download_wordpress_files();
	}
);

$steps->Given( '/^wp-config\.php$/',
	function ( $world ) {
		$world->proc( 'core config' )->run_check();
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
		$world->proc( 'core install-network' )->run_check();
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

$steps->Given( '/^a P2 theme zip$/',
	function ( $world ) {
		$zip_name = 'p2.1.0.1.zip';

		$world->variables['THEME_ZIP'] = $world->get_cache_path( $zip_name );

		$zip_url = 'http://wordpress.org/extend/themes/download/' . $zip_name;

		$world->download_file( $zip_url, $world->variables['THEME_ZIP'] );
	}
);

$steps->Given( '/^a large image file$/',
	function ( $world ) {
		$image_file = 'http://wordpresswallpaper.com/wp-content/gallery/photo-based-wallpaper/1058.jpg';

		$world->variables['DOWNLOADED_IMAGE'] = $world->get_cache_path( 'wallpaper.jpg' );

		$world->download_file( $image_file, $world->variables['DOWNLOADED_IMAGE'] );
	}
);

$steps->When( '/^I run `wp`$/',
	function ( $world ) {
		$world->result = $world->proc( '' )->run();
	}
);

$steps->When( '/^I run `wp (.+)`$/',
	function ( $world, $cmd ) {
		$world->result = $world->proc( $world->replace_variables( $cmd ) )->run();
	}
);

$steps->When( "/^I run `wp (.+)` from '([^\s]+)'$/",
	function ( $world, $cmd, $subdir ) {
		$world->result = $world->proc( $world->replace_variables( $cmd ) )->run( $subdir );
	}
);

$steps->When( '/^I run the previous command again$/',
	function ( $world ) {
		if ( !isset( $world->result ) )
			throw new \Exception( 'No previous command.' );

		$world->result = Process::create( $world->result->command, $world->result->cwd )->run();
	}
);

$steps->When( '/^I try to import it$/',
	function ( $world ) {
		if ( !isset( $world->variables['DOWNLOADED_IMAGE'] ) )
			throw new \Exception( 'Cached image not available.' );

		$world->result = $world->proc( 'media import ' . $world->variables['DOWNLOADED_IMAGE'] . ' --post_id=1 --featured_image' )->run();
	}
);

$steps->Given( '/^save (STDOUT|STDERR) as \{(\w+)\}$/',
	function ( $world, $stream, $key ) {
		$world->variables[ $key ] = rtrim( $world->result->$stream, "\n" );
	}
);

$steps->Then( '/^the return code should be (\d+)$/',
	function ( $world, $return_code ) {
		assertEquals( $return_code, $world->result->return_code );
	}
);

$steps->Then( '/^it should run without errors$/',
	function ( $world ) {
		if ( !empty( $world->result->STDERR ) ) {
			$r = $world->result;
			throw new \Exception( sprintf( "%s: %s\ncwd: %s",
				$r->command, $r->STDERR, $r->cwd ) );
		}

		if ( 0 != $world->result->return_code )
			throw new \Exception( "Return code was $world->result->return_code" );
	}
);

$steps->Then( '/^(STDOUT|STDERR) should (be|contain|not contain):$/',
	function ( $world, $stream, $action, PyStringNode $expected ) {
		$output = $world->result->$stream;

		$expected = $world->replace_variables( (string) $expected );

		switch ( $action ) {

		case 'be':
			$r = $expected === rtrim( $output, "\n" );
			break;

		case 'contain':
			$r = false !== strpos( $output, $expected );
			break;

		case 'not contain':
			$r = false === strpos( $output, $expected );
			break;

		default:
			throw new PendingException();
		}

		if ( !$r ) {
			throw new \Exception( $output );
		}
	}
);

$steps->Then( '/^(STDOUT|STDERR) should match \'([^\']+)\'$/',
	function ( $world, $stream, $format ) {
		assertStringMatchesFormat( $format, $world->result->$stream );
	}
);

$steps->Then( '/^STDOUT should be a table containing rows:$/',
	function ( $world, PyStringNode $expected ) {
		$output     = $world->result->STDOUT;
		$outputRows = explode( "\n", rtrim( $output, "\n" ) );

		$expected     = $world->replace_variables( (string) $expected );
		$expectedRows = explode( "\n", rtrim( $expected, "\n" ) );

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
		$output     = $world->result->STDOUT;

		$expected     = $world->replace_variables( (string) $expected );

		if ( !checkThatJsonStringContainsJsonString( $output,
		                                             $expected ) ) {
			throw new \Exception( $output );
		}
});

$steps->Then( '/^STDOUT should be CSV containing:$/',
	function( $world, PyStringNode $expected ) {

		$output        = $world->result->STDOUT;
		$expected      = $world->replace_variables( (string) $expected );

		if ( ! checkThatCsvStringContainsCsvString( $output, $expected ) )
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

$steps->Then( '/^the (.+) file should exist$/',
	function ( $world, $path ) {
		$path = $world->replace_variables( $path );

		// If it's a relative path, make it relative to the current test dir
		if ( '/' !== $path[0] )
			$path = $world->get_path( $path );

		assertFileExists( $path );
	}
);

/**
 * Compare two strings containing JSON to ensure that @a $actualJson contains at
 * least what the JSON string @a $expectedJson contains.
 *
 * @return whether or not @a $actualJson contains @a $expectedJson
 *     @retval true  @a $actualJson contains @a $expectedJson
 *     @retval false @a $actualJson does not contain @a $expectedJson
 *
 * @param[in] $actualJson   the JSON string to be tested
 * @param[in] $expectedJson the expected JSON string
 *
 * Examples:
 *   expected: {'a':1,'array':[1,3,5]}
 *
 *   1)
 *   actual: {'a':1,'b':2,'c':3,'array':[1,2,3,4,5]}
 *   return: true
 *
 *   2)
 *   actual: {'b':2,'c':3,'array':[1,2,3,4,5]}
 *   return: false
 *     element 'a' is missing from the root object
 *
 *   3)
 *   actual: {'a':0,'b':2,'c':3,'array':[1,2,3,4,5]}
 *   return: false
 *     the value of element 'a' is not 1
 *
 *   4)
 *   actual: {'a':1,'b':2,'c':3,'array':[1,2,4,5]}
 *   return: false
 *     the contents of 'array' does not include 3
 */
function checkThatJsonStringContainsJsonString( $actualJson, $expectedJson ) {
	$actualValue   = json_decode( $actualJson );
	$expectedValue = json_decode( $expectedJson );

	if ( !$actualValue ) {
		return false;
	}

	return compareContents( $expectedValue, $actualValue );
}

function compareContents( $expected, $actual ) {
	if ( gettype( $expected ) != gettype( $actual ) ) {
		return false;
	}

	if ( is_object( $expected ) ) {
		foreach ( get_object_vars( $expected ) as $name => $value ) {
			if ( ! compareContents( $value, $actual->$name ) )
				return false;
		}
	} else if ( is_array( $expected ) ) {
		foreach ( $expected as $key => $value ) {
			if ( ! compareContents( $value, $actual[$key] ) )
				return false;
		}
	} else {
		return $expected === $actual;
	}

	return true;
}

/**
 * Compare two strings to confirm $actualCSV contains $expectedCSV
 * Both strings are expected to have headers for their CSVs.
 * $actualCSV must match all data rows in $expectedCSV
 *
 * @return bool     Whether $actualCSV contacts $expectedCSV
 */
function checkThatCsvStringContainsCsvString( $actualCSV, $expectedCSV ) {

	$actualCSV = array_map( 'str_getcsv', explode( PHP_EOL, $actualCSV ) );
	$expectedCSV = array_map( 'str_getcsv', explode( PHP_EOL, $expectedCSV ) );

	if ( empty( $actualCSV ) )
		return false;

	// Each sample must have headers
	$actualHeaders = array_values( array_shift( $actualCSV ) );
	$expectedHeaders = array_values( array_shift( $expectedCSV ) );

	// Each expectedCSV must exist somewhere in actualCSV in the proper column
	$expectedResult = 0;
	foreach( $expectedCSV as $expected_row ) {
		$expected_row = array_combine( $expectedHeaders, $expected_row );
		foreach( $actualCSV as $actual_row ) {

			if ( count( $actualHeaders ) != count( $actual_row ) )
				continue;

			$actual_row = array_intersect_key( array_combine( $actualHeaders, $actual_row ), $expected_row );
			if ( $actual_row == $expected_row )
				$expectedResult++;
		}
	}

	return $expectedResult >= count( $expectedCSV );
}
