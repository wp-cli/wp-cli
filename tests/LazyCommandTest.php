<?php

use WP_CLI\Dispatcher\CompositeCommand;
use WP_CLI\Dispatcher\LazyCommand;
use WP_CLI\DocParser;
use WP_CLI\ExitException;
use WP_CLI\Tests\TestCase;

class LazyCommandTest extends TestCase {

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

	public function set_up(): void {
		parent::set_up();

		$capture_exit = new \ReflectionProperty( 'WP_CLI', 'capture_exit' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$capture_exit->setAccessible( true );
		}
		$this->prev_capture_exit = $capture_exit->getValue();
		$capture_exit->setValue( null, true );

		$this->prev_logger = WP_CLI::get_logger();
		$this->logger      = new WP_CLI\Loggers\Execution();
		WP_CLI::set_logger( $this->logger );
	}

	public function tear_down(): void {
		$capture_exit = new \ReflectionProperty( 'WP_CLI', 'capture_exit' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$capture_exit->setAccessible( true );
		}
		$capture_exit->setValue( null, $this->prev_capture_exit );

		WP_CLI::set_logger( $this->prev_logger );

		parent::tear_down();
	}

	public function test_missing_class_error_names_the_full_command(): void {
		$parent = new CompositeCommand( WP_CLI::get_root_command(), 'lazy-parent', new DocParser( '' ) );
		// @phpstan-ignore argument.type
		$command = new LazyCommand( $parent, 'child', 'WP_CLI_Missing_Lazy_Command_Class' );

		$this->expectException( ExitException::class );

		try {
			$command->materialize();
		} finally {
			$this->assertStringContainsString(
				'Callable "WP_CLI_Missing_Lazy_Command_Class" does not exist, and cannot be registered as `wp lazy-parent child`.',
				$this->logger->stderr
			);
		}
	}
}
