<?php

use Behat\Behat\Exception\PendingException,
    Behat\Gherkin\Node\PyStringNode,
    Behat\Gherkin\Node\TableNode;

$steps->Given( '/^an empty directory$/',
	function ( $world ) {
		$world->create_empty_dir();
	}
);

$steps->Given( '/^WP files$/',
	function ( $world ) {
		$world->download_wordpress_files();
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
		$world->create_db();
		$world->create_empty_dir();
		$world->download_wordpress_files();
		$world->create_config();
		$world->run_install();
	}
);

$steps->Given( '/^custom wp-content directory$/',
	function ( $world ) {
		$world->define_custom_wp_content_dir();
	}
);

$steps->Given('/^a P2 theme zip$/',
	function ( $world ) {
		$zip_name = 'p2.1.0.1.zip';

		$cache_dir = sys_get_temp_dir() . '/wp-cli-test-cache';
		$world->variables['THEME_ZIP'] = $cache_dir . '/' . $zip_name;

		$zip_url = 'http://wordpress.org/extend/themes/download/' . $zip_name;

		system( \WP_CLI\Utils\create_cmd( 'mkdir -p %s', $cache_dir ) );

		system( \WP_CLI\Utils\create_cmd( 'curl -s %s > %s', $zip_url,
			$world->variables['THEME_ZIP'] ) );
	}
);

$steps->When( '/^I run `wp`$/',
	function ( $world ) {
		$world->result = $world->run( '' );
	}
);

$steps->When( '/^I run `wp (.+)`$/',
	function ( $world, $cmd ) {
		$world->result = $world->run( $world->replace_variables( $cmd ) );
	}
);

$steps->When( '/^I run the previous command again$/',
	function ( $world ) {
		if ( !isset( $world->result ) )
			throw new \Exception( 'No previous command.' );

		$world->result = $world->run( $world->result->command );
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
		if ( !empty( $world->result->STDERR ) )
			throw new \Exception( $world->result->STDERR );

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
		assertFileExists( $world->get_path( $path ) );
	}
);
