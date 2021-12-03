<?php
// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound -- Ignoring test doubles.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedClassFound -- Ignoring test doubles.

use WP_CLI\Tests\TestCase;

class MockRegularLogger extends WP_CLI\Loggers\Regular {

	protected function get_runner() {
		return (object) [
			'config' => [
				'debug' => true,
			],
		];
	}

	protected function write( $handle, $str ) {
		echo $str;
	}
}

class MockQuietLogger extends WP_CLI\Loggers\Quiet {

	protected function get_runner() {
		return (object) [
			'config' => [
				'debug' => true,
			],
		];
	}
}

class LoggingTests extends TestCase {

	public function testLogDebug() {
		$message = 'This is a test message.';

		$regular_logger = new MockRegularLogger( false );
		$this->expectOutputRegex( "/Debug: {$message} \(\d+\.*\d*s\)/" );
		$regular_logger->debug( $message );

		$quiet_logger = new MockQuietLogger();
		$this->expectOutputRegex( "/Debug: {$message} \(\d+\.*\d*s\)/" );
		$quiet_logger->debug( $message );
	}

	public function testLogEscaping() {
		$logger = new MockRegularLogger( false );

		$message = 'foo%20bar';

		$this->expectOutputString( "Success: $message\n" );
		$logger->success( $message );
	}

	public function testExecutionLogger() {
		// Save Runner config.
		$runner        = WP_CLI::get_runner();
		$runner_config = new \ReflectionProperty( $runner, 'config' );
		$runner_config->setAccessible( true );

		$prev_config = $runner_config->getValue( $runner );

		// Set debug.
		$runner_config->setValue( $runner, [ 'debug' => true ] );

		$logger = new WP_CLI\Loggers\Execution();

		// Standard use.

		$logger->info( 'info' );
		$logger->info( 'info2' );
		$logger->success( 'success' );
		$logger->warning( 'warning' );
		$logger->error( 'error' );
		$logger->success( 'success2' );
		$logger->warning( 'warning2' );
		$logger->debug( 'debug', 'group' );
		$logger->error_multi_line( [ 'line11', 'line12', 'line13' ] );
		$logger->error( 'error2' );
		$logger->error_multi_line( [ 'line21' ] );
		$logger->debug( 'debug2', 'group2' );

		$this->assertSame( "info\ninfo2\nSuccess: success\nSuccess: success2\n", $logger->stdout );

		$match_count = preg_match(
			'/^'
				. 'Warning: warning\nError: error\n'
				. 'Warning: warning2\nDebug \(group\): debug \([0-9.]+s\)\n'
				. 'Error:\nline11\nline12\nline13\n---------\n\nError: error2\n'
				. 'Error:\nline21\n---------\n\nDebug \(group2\): debug2 \([0-9.]+s\)$/',
			$logger->stderr
		);
		$this->assertSame( 1, $match_count );

		$logger->stdout = '';
		$logger->stderr = '';

		// With output buffering.

		$logger->ob_start();

		echo 'echo';
		$logger->info( 'info' );
		print "print\n";
		$logger->success( 'success' );
		echo "echo2\n";
		$logger->error( 'error' );
		echo "echo3\n";
		$logger->success( 'success2' );
		echo 'echo4';

		$logger->ob_end();

		$this->assertSame( "echoinfo\nprint\nSuccess: success\necho2\necho3\nSuccess: success2\necho4", $logger->stdout );
		$this->assertSame( "Error: error\n", $logger->stderr );

		$logger->stdout = '';
		$logger->stderr = '';

		// Restore.
		$runner_config->setValue( $runner, $prev_config );
	}
}
