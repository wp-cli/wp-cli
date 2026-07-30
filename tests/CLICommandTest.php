<?php

use WP_CLI\Tests\TestCase;
use WP_CLI\ExitException;

/**
 * @phpstan-import-type UpdateOffer from CLI_Command
 */
class CLICommandTest extends TestCase {

	/**
	 * @var bool|mixed
	 */
	private $prev_capture_exit;

	/**
	 * @var \WP_CLI\Loggers\Base
	 */
	private $prev_logger;

	/**
	 * @var \WP_CLI\Loggers\Execution
	 */
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

	/**
	 * @param array<array-key, UpdateOffer> $updates
	 * @param bool                           $include_major
	 * @return UpdateOffer|null
	 */
	private function call_select_update_offer( $updates, $include_major ) {
		$cli_command = new CLI_Command();
		$method      = new \ReflectionMethod( $cli_command, 'select_update_offer' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$method->setAccessible( true );
		}
		/**
		 * @var UpdateOffer|null $result
		 */
		$result = $method->invoke( $cli_command, $updates, $include_major );

		return $result;
	}

	/**
	 * @param string $update_type
	 * @param string $version
	 * @param string $status
	 * @return UpdateOffer
	 */
	private function update_offer( $update_type, $version, $status = 'available' ) {
		return [
			'version'      => $version,
			'update_type'  => $update_type,
			'package_url'  => "https://example.com/wp-cli-{$version}.phar",
			'status'       => $status,
			'requires_php' => '',
		];
	}

	public function testSelectUpdateOfferSkipsMajorByDefaultAndPicksMinor(): void {
		$updates = [
			'major' => $this->update_offer( 'major', '3.0.0' ),
			'minor' => $this->update_offer( 'minor', '2.12.0' ),
			'patch' => $this->update_offer( 'patch', '2.11.5' ),
		];

		$result = $this->call_select_update_offer( $updates, false );

		$this->assertSame( '2.12.0', $result['version'] ?? null );
		$this->assertSame( '', $this->logger->stdout );
	}

	public function testSelectUpdateOfferFallsBackToPatchWhenNoMinor(): void {
		$updates = [
			'major' => $this->update_offer( 'major', '3.0.0' ),
			'patch' => $this->update_offer( 'patch', '2.11.5' ),
		];

		$result = $this->call_select_update_offer( $updates, false );

		$this->assertSame( '2.11.5', $result['version'] ?? null );
		$this->assertSame( '', $this->logger->stdout );
	}

	public function testSelectUpdateOfferReturnsMajorWhenRequested(): void {
		$updates = [
			'major' => $this->update_offer( 'major', '3.0.0' ),
			'minor' => $this->update_offer( 'minor', '2.12.0' ),
		];

		$result = $this->call_select_update_offer( $updates, true );

		$this->assertSame( '3.0.0', $result['version'] ?? null );
		$this->assertSame( '', $this->logger->stdout );
	}

	public function testSelectUpdateOfferWithholdsLoneMajorByDefault(): void {
		$updates = [
			'major' => $this->update_offer( 'major', '3.0.0' ),
		];

		$result = $this->call_select_update_offer( $updates, false );

		$this->assertNull( $result );
		$this->assertSame(
			"A new major version (3.0.0) is available. Run `wp cli update --major` to update across major versions.\n",
			$this->logger->stdout
		);
	}

	public function testSelectUpdateOfferDoesNotHintUnavailableMajor(): void {
		$updates = [
			'major' => $this->update_offer( 'major', '3.0.0', 'unavailable' ),
			'minor' => $this->update_offer( 'minor', '2.12.0' ),
		];

		$result = $this->call_select_update_offer( $updates, false );

		$this->assertSame( '2.12.0', $result['version'] ?? null );
		$this->assertSame( '', $this->logger->stdout );
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
