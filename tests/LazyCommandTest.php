<?php

use Composer\Autoload\ClassLoader;
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

	public function test_class_is_loaded_by_the_autoloader_that_located_it_on_registration(): void {
		$dir = sys_get_temp_dir() . '/wp-cli-lazy-command-' . uniqid();
		mkdir( $dir . '/registered', 0777, true );
		mkdir( $dir . '/later', 0777, true );

		$class_name = 'WP_CLI_Lazy_Command_Loader_Test_' . uniqid();
		foreach ( [ 'registered', 'later' ] as $source ) {
			file_put_contents(
				"{$dir}/{$source}/{$class_name}.php",
				"<?php class {$class_name} extends WP_CLI_Command { const SOURCE = '{$source}'; /** Does nothing. */ public function noop() {} }"
			);
		}

		$registered_loader = new ClassLoader();
		$registered_loader->addClassMap( [ $class_name => "{$dir}/registered/{$class_name}.php" ] );

		$later_loader = new ClassLoader();
		$later_loader->addClassMap( [ $class_name => "{$dir}/later/{$class_name}.php" ] );
		$later_loader->register( true );

		$runner        = WP_CLI::get_runner();
		$runner_config = new \ReflectionProperty( $runner, 'config' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$runner_config->setAccessible( true );
		}
		$prev_config = $runner_config->getValue( $runner );
		$runner_config->setValue( $runner, [ 'debug' => false ] );

		try {
			$parent = new CompositeCommand( WP_CLI::get_root_command(), 'lazy-parent', new DocParser( '' ) );
			// @phpstan-ignore argument.type
			$command = new LazyCommand( $parent, 'child', $class_name, $registered_loader );
			$command->materialize();

			$this->assertSame( 'registered', constant( "{$class_name}::SOURCE" ) );
		} finally {
			$runner_config->setValue( $runner, $prev_config );
			$later_loader->unregister();
			foreach ( [ 'registered', 'later' ] as $source ) {
				unlink( "{$dir}/{$source}/{$class_name}.php" );
				rmdir( "{$dir}/{$source}" );
			}
			rmdir( $dir );
		}
	}
}
