<?php

use WP_CLI\Tests\TestCase;
use WP_CLI\ExitException;

class CLICommandTest extends TestCase {

	private $prev_capture_exit;
	private $prev_logger;
	private $logger;

	public static function set_up_before_class() {
		require_once WP_CLI_ROOT . '/php/commands/src/CLI_Command.php';
	}

	public function setUp(): void {
		parent::setUp();

		// Save state.
		$class_wp_cli_capture_exit = new \ReflectionProperty( 'WP_CLI', 'capture_exit' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$class_wp_cli_capture_exit->setAccessible( true );
		}
		$this->prev_capture_exit = $class_wp_cli_capture_exit->getValue();
		$class_wp_cli_capture_exit->setValue( null, true );

		$this->prev_logger = WP_CLI::get_logger();
		$this->logger      = new WP_CLI\Loggers\Execution();
		WP_CLI::set_logger( $this->logger );
	}

	public function tearDown(): void {
		// Restore state.
		$class_wp_cli_capture_exit = new \ReflectionProperty( 'WP_CLI', 'capture_exit' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$class_wp_cli_capture_exit->setAccessible( true );
		}
		$class_wp_cli_capture_exit->setValue( null, $this->prev_capture_exit );

		WP_CLI::set_logger( $this->prev_logger );

		parent::tearDown();
	}

	private function call_replace_current_phar( $temp, $current_phar ) {
		$cli_command = new CLI_Command();
		$method      = new \ReflectionMethod( $cli_command, 'replace_current_phar' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$method->setAccessible( true );
		}
		$method->invoke( $cli_command, $temp, $current_phar );
	}

	public function testReplaceCurrentPharNonWindowsSuccess(): void {
		if ( WP_CLI\Utils\is_windows() ) {
			$this->markTestSkipped( 'Not applicable on Windows' );
		}

		$temp         = tempnam( sys_get_temp_dir(), 'wp-cli-temp-' );
		$current_phar = tempnam( sys_get_temp_dir(), 'wp-cli-current-' );

		file_put_contents( $temp, 'new content' );
		file_put_contents( $current_phar, 'old content' );

		$this->call_replace_current_phar( $temp, $current_phar );

		$this->assertFileExists( $current_phar );
		$this->assertSame( 'new content', file_get_contents( $current_phar ) );
		$this->assertFileDoesNotExist( $temp );

		@unlink( $current_phar );
	}

	public function testReplaceCurrentPharNonWindowsFailure(): void {
		if ( WP_CLI\Utils\is_windows() ) {
			$this->markTestSkipped( 'Not applicable on Windows' );
		}

		$temp         = tempnam( sys_get_temp_dir(), 'wp-cli-temp-' );
		$current_phar = '/nonexistent/dir/wp-cli.phar'; // Invalid path to trigger rename failure.

		file_put_contents( $temp, 'new content' );

		$this->expectException( ExitException::class );

		try {
			$this->call_replace_current_phar( $temp, $current_phar );
		} finally {
			$this->assertFileDoesNotExist( $temp ); // Verify cleanup.
			$this->assertStringContainsString( 'Cannot move', $this->logger->stderr );
		}
	}

	public function testReplaceCurrentPharWindowsSuccess(): void {
		if ( ! WP_CLI\Utils\is_windows() ) {
			$this->markTestSkipped( 'Windows only test' );
		}

		$temp         = tempnam( sys_get_temp_dir(), 'wp-cli-temp-' );
		$current_phar = tempnam( sys_get_temp_dir(), 'wp-cli-current-' );
		$bak_file     = $current_phar . '.bak';

		file_put_contents( $temp, 'new content' );
		file_put_contents( $current_phar, 'old content' );
		file_put_contents( $bak_file, 'stale backup' );

		$this->call_replace_current_phar( $temp, $current_phar );

		$this->assertFileExists( $current_phar );
		$this->assertSame( 'new content', file_get_contents( $current_phar ) );
		$this->assertFileDoesNotExist( $temp );
		$this->assertFileDoesNotExist( $bak_file );

		@unlink( $current_phar );
	}

	public function testReplaceCurrentPharWindowsStaleBackupDeletionFailure(): void {
		if ( ! WP_CLI\Utils\is_windows() ) {
			$this->markTestSkipped( 'Windows only test' );
		}

		$temp         = tempnam( sys_get_temp_dir(), 'wp-cli-temp-' );
		$current_phar = tempnam( sys_get_temp_dir(), 'wp-cli-current-' );
		$bak_file     = $current_phar . '.bak';

		file_put_contents( $temp, 'new content' );
		file_put_contents( $current_phar, 'old content' );
		mkdir( $bak_file ); // Make it a directory to cause unlink failure.

		$this->expectException( ExitException::class );

		try {
			$this->call_replace_current_phar( $temp, $current_phar );
		} finally {
			$this->assertFileDoesNotExist( $temp ); // Verify cleanup.
			$this->assertFileExists( $bak_file ); // Stale backup is still there because unlink failed.
			$this->assertStringContainsString( 'Cannot remove existing backup', $this->logger->stderr );

			rmdir( $bak_file );
			@unlink( $current_phar );
		}
	}

	public function testReplaceCurrentPharWindowsRenameToBackupFailure(): void {
		if ( ! WP_CLI\Utils\is_windows() ) {
			$this->markTestSkipped( 'Windows only test' );
		}

		$temp         = tempnam( sys_get_temp_dir(), 'wp-cli-temp-' );
		$current_phar = '/nonexistent/dir/wp-cli.phar'; // Invalid path to trigger backup failure.

		file_put_contents( $temp, 'new content' );

		$this->expectException( ExitException::class );

		try {
			$this->call_replace_current_phar( $temp, $current_phar );
		} finally {
			$this->assertFileDoesNotExist( $temp ); // Verify cleanup.
			$this->assertStringContainsString( 'Cannot rename', $this->logger->stderr );
		}
	}

	public function testReplaceCurrentPharWindowsMoveFailureReverts(): void {
		if ( ! WP_CLI\Utils\is_windows() ) {
			$this->markTestSkipped( 'Windows only test' );
		}

		$temp         = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'wp-cli-nonexistent-temp-' . uniqid();
		$current_phar = tempnam( sys_get_temp_dir(), 'wp-cli-current-' );
		$bak_file     = $current_phar . '.bak';

		file_put_contents( $current_phar, 'old content' );

		$this->expectException( ExitException::class );

		try {
			$this->call_replace_current_phar( $temp, $current_phar );
		} finally {
			$this->assertFileDoesNotExist( $temp );
			$this->assertFileExists( $current_phar ); // Reverted backup back to original.
			$this->assertSame( 'old content', file_get_contents( $current_phar ) );
			$this->assertFileDoesNotExist( $bak_file ); // Bak file is gone.
			$this->assertStringContainsString( 'Cannot move', $this->logger->stderr );
			$this->assertStringContainsString( 'The original Phar was successfully restored.', $this->logger->stderr );

			@unlink( $current_phar );
		}
	}
}
