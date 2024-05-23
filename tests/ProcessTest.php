<?php

use WP_CLI\Process;
use WP_CLI\Tests\TestCase;
use WP_CLI\Utils;

class ProcessTest extends TestCase {

	/**
	 * @dataProvider data_process_env
	 */
	public function test_process_env( $cmd_prefix, $env, $expected_env_vars, $expected_out ) {
		$code = vsprintf( str_repeat( 'echo getenv( \'%s\' );', count( $expected_env_vars ) ), $expected_env_vars );

		$cmd         = $cmd_prefix . ' ' . escapeshellarg( Utils\get_php_binary() ) . ' -r ' . escapeshellarg( $code );
		$process_run = Process::create( $cmd, null /*cwd*/, $env )->run();

		$this->assertSame( $process_run->stdout, $expected_out );
	}

	public static function data_process_env() {
		return [
			[ '', [], [], '' ],
			[ 'ENV=blah', [], [ 'ENV' ], 'blah' ],
			[ 'ENV="blah blah"', [], [ 'ENV' ], 'blah blah' ],
			[ 'ENV_1="blah1 blah1" ENV_2="blah2" ENV_3=blah3', [ 'ENV' => 'in' ], [ 'ENV', 'ENV_1', 'ENV_2', 'ENV_3' ], 'inblah1 blah1blah2blah3' ],
			[ 'ENV=blah', [ 'ENV_1' => 'in1', 'ENV_2' => 'in2' ], [ 'ENV_1', 'ENV_2', 'ENV' ], 'in1in2blah' ],
		];
	}
}
