<?php

use WP_CLI\Bootstrap\BootstrapState;
use WP_CLI\Bootstrap\LoadRequiredCommand;
use WP_CLI\Loggers;
use WP_CLI\Tests\TestCase;

class LoadRequiredCommandTest extends TestCase {

	/**
	 * @var string|false
	 */
	private $previous_env;

	/**
	 * @var string[]
	 */
	private $files = [];

	/**
	 * @var array<mixed, mixed>
	 */
	private $previous_config = [];

	/**
	 * @var Loggers\Base|null
	 */
	private $previous_logger;

	public function set_up(): void {
		parent::set_up();

		$this->previous_env    = getenv( 'WP_CLI_REQUIRE' );
		$this->previous_logger = WP_CLI::get_logger();
		WP_CLI::set_logger( new Loggers\Execution() );

		$GLOBALS['wp_cli_load_required_command_test'] = [];

		WP_CLI::get_runner()->init_config();
		$this->previous_config = $this->get_runner_config();
	}

	public function tear_down(): void {
		putenv( false === $this->previous_env ? 'WP_CLI_REQUIRE' : "WP_CLI_REQUIRE={$this->previous_env}" );
		$this->set_runner_config( $this->previous_config );

		if ( null !== $this->previous_logger ) {
			WP_CLI::set_logger( $this->previous_logger );
		}

		foreach ( $this->files as $file ) {
			unlink( $file );
		}
		$this->files = [];

		unset( $GLOBALS['wp_cli_load_required_command_test'] );

		parent::tear_down();
	}

	public function testLoadsAllRequiredFilesForRegularCommands(): void {
		$env_file = $this->create_required_file( 'env' );
		$cmd_file = $this->create_required_file( 'cmd' );

		putenv( "WP_CLI_REQUIRE={$env_file}" );
		$this->set_runner_require( [ $env_file, $cmd_file ] );

		( new LoadRequiredCommand() )->process( new BootstrapState() );

		$this->assertSame( [ 'env', 'cmd' ], $GLOBALS['wp_cli_load_required_command_test'] );
	}

	public function testLoadsOnlyEnvRequiredFilesForProtectedCommands(): void {
		$env_file = $this->create_required_file( 'env' );
		$cmd_file = $this->create_required_file( 'cmd' );

		putenv( "WP_CLI_REQUIRE={$env_file}" );
		$this->set_runner_require( [ $cmd_file, $env_file ] );

		$state = new BootstrapState();
		$state->setValue( BootstrapState::IS_PROTECTED_COMMAND, true );

		( new LoadRequiredCommand() )->process( $state );

		$this->assertSame( [ 'env' ], $GLOBALS['wp_cli_load_required_command_test'] );
	}

	public function testLoadsNothingForProtectedCommandsWithoutEnvRequiredFiles(): void {
		$cmd_file = $this->create_required_file( 'cmd' );

		putenv( 'WP_CLI_REQUIRE' );
		$this->set_runner_require( [ $cmd_file ] );

		$state = new BootstrapState();
		$state->setValue( BootstrapState::IS_PROTECTED_COMMAND, true );

		( new LoadRequiredCommand() )->process( $state );

		$this->assertSame( [], $GLOBALS['wp_cli_load_required_command_test'] );
	}

	/**
	 * Create a PHP file that records that it was loaded.
	 *
	 * @param string $marker Marker to record when the file is loaded.
	 * @return string Path to the created file.
	 */
	private function create_required_file( $marker ): string {
		$file = (string) tempnam( sys_get_temp_dir(), 'wp-cli-test-require-' );
		file_put_contents( $file, "<?php\n\$GLOBALS['wp_cli_load_required_command_test'][] = '{$marker}';\n" );
		$this->files[] = $file;

		return $file;
	}

	/**
	 * @param string[] $files
	 */
	private function set_runner_require( array $files ): void {
		$config            = $this->get_runner_config();
		$config['require'] = $files;
		$this->set_runner_config( $config );
	}

	/**
	 * @return array<mixed, mixed>
	 */
	private function get_runner_config(): array {
		return (array) $this->get_config_property()->getValue( WP_CLI::get_runner() );
	}

	/**
	 * @param array<mixed, mixed> $config
	 */
	private function set_runner_config( array $config ): void {
		$this->get_config_property()->setValue( WP_CLI::get_runner(), $config );
	}

	private function get_config_property(): ReflectionProperty {
		$property = new ReflectionProperty( 'WP_CLI\Runner', 'config' );
		if ( PHP_VERSION_ID < 80100 ) {
			// @phpstan-ignore method.deprecated
			$property->setAccessible( true );
		}

		return $property;
	}
}
