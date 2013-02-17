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

$steps->Given( '/^WP install$/',
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

$steps->When( '/^I run `(.+)`$/',
	function ( $world, $cmd ) {
		$cmd = ltrim( str_replace( 'wp', '', $cmd ) );

		$world->replace_variables( $cmd );

		$world->result = $world->run( $cmd );
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

$steps->Then( '/^(STDOUT|STDERR) should be:$/',
	function ( $world, $stream, PyStringNode $output ) {
		$world->replace_variables( $output );

		$result = rtrim( $world->result->$stream, "\n" );

		if ( (string) $output != $result ) {
			throw new \Exception( $world->result->$stream );
		}
	}
);

$steps->Then( '/^(STDOUT|STDERR) should contain:$/',
	function ( $world, $stream, PyStringNode $output ) {
		if ( false === strpos( $world->result->$stream, (string) $output ) ) {
			throw new \Exception( $world->result->$stream );
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
