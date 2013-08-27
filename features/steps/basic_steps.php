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
		$world->create_run_dir();
	}
);

$steps->Given( '/^a ([^\s]+) file:$/',
	function ( $world, $path, PyStringNode $content ) {
		$content = (string) $content . "\n";
		$full_path = $world->variables['RUN_DIR'] . "/$path";
		Process::create( \WP_CLI\utils\esc_cmd( 'mkdir -p %s', dirname( $full_path ) ) )->run_check();
		file_put_contents( $full_path, $content );
	}
);

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

$steps->Given( '/^a WP multisite install$/',
	function ( $world ) {
		$world->install_wp();
		$world->proc( 'wp core install-network', array( 'title' => 'WP CLI Network' ) )->run_check();
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

			\Process::create( \WP_CLI\Utils\esc_cmd( 'curl -sSL %s > %s', $row['url'], $path ) )->run_check();
		}
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
		if ( $return_code != $world->result->return_code ) {
			throw new RuntimeException( $world->result );
		}
	}
);

$steps->Then( '/^(STDOUT|STDERR) should (be|contain|not contain):$/',
	function ( $world, $stream, $action, PyStringNode $expected ) {
		$expected = $world->replace_variables( (string) $expected );

		checkString( $world->result->$stream, $expected, $action );
	}
);

$steps->Then( '/^(STDOUT|STDERR) should be a number$/',
	function ( $world, $stream ) {
		assertNumeric( trim( $world->result->$stream, "\n" ) );
	}
);

$steps->Then( '/^STDOUT should be a table containing rows:$/',
	function ( $world, TableNode $expected ) {
		$output      = $world->result->STDOUT;
		$actual_rows = explode( "\n", rtrim( $output, "\n" ) );

		$expected_rows = array();
		foreach ( $expected->getRows() as $row ) {
			$expected_rows[] = $world->replace_variables( implode( "\t", $row ) );
		}

		compareTables( $expected_rows, $actual_rows, $output );
	}
);

$steps->Then( '/^STDOUT should end with a table containing rows:$/',
	function ( $world, TableNode $expected ) {
		$output      = $world->result->STDOUT;
		$actual_rows = explode( "\n", rtrim( $output, "\n" ) );

		$expected_rows = array();
		foreach ( $expected->getRows() as $row ) {
			$expected_rows[] = $world->replace_variables( implode( "\t", $row ) );
		}

		$start = array_search( $expected_rows[0], $actual_rows );

		if ( false === $start )
			throw new \Exception( $output );

		compareTables( $expected_rows, array_slice( $actual_rows, $start ), $output );
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
	function ( $world, TableNode $expected ) {
		$output = $world->result->STDOUT;

		$expected_rows = $expected->getRows();
		foreach ( $expected as &$row ) {
			foreach ( $row as &$value ) {
				$value = $world->replace_variables( $value );
			}
		}

		if ( ! checkThatCsvStringContainsValues( $output, $expected_rows ) )
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
		if ( '' === rtrim( $world->result->$stream, "\n" ) ) {
			throw new Exception( "$stream is empty." );
		}
	}
);

$steps->Then( '/^the (.+) file should (exist|not exist|be:|contain:|not contain:)$/',
	function ( $world, $path, $action, $expected = null ) {
		$path = $world->replace_variables( $path );

		// If it's a relative path, make it relative to the current test dir
		if ( '/' !== $path[0] )
			$path = $world->variables['RUN_DIR'] . "/$path";

		switch ( $action ) {
		case 'exist':
			if ( !file_exists( $path ) ) {
				throw new Exception( "$path doesn't exist." );
			}
			break;
		case 'not exist':
			if ( file_exists( $path ) ) {
				throw new Exception( "$path exists." );
			}
			break;
		default:
			if ( !file_exists( $path ) ) {
				throw new Exception( "$path doesn't exist." );
			}
			$action = substr( $action, 0, -1 );
			$expected = $world->replace_variables( (string) $expected );
			checkString( file_get_contents( $path ), $expected, $action );
		}
	}
);

