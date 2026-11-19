<?php

use WP_CLI\Tests\TestCase;

class RequestsLibraryTest extends TestCase {

	/**
	 * @var string[]
	 */
	private $temp_dirs = [];

	public function tear_down(): void {
		foreach ( $this->temp_dirs as $dir ) {
			$this->remove_dir( $dir );
		}
		$this->temp_dirs = [];

		parent::tear_down();
	}

	private function create_temp_dir(): string {
		$dir = sys_get_temp_dir() . '/wp-cli-requests-test-' . uniqid( '', true );
		mkdir( $dir, 0777, true );
		$this->temp_dirs[] = $dir;

		return $dir;
	}

	private function remove_dir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = scandir( $dir );
		if ( false === $items ) {
			return;
		}

		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}
			$path = $dir . '/' . $item;
			if ( is_dir( $path ) ) {
				$this->remove_dir( $path );
			} else {
				unlink( $path );
			}
		}

		rmdir( $dir );
	}

	/**
	 * Run a PHP snippet in an isolated subprocess where Requests is not preloaded.
	 *
	 * @param string $code PHP snippet to execute after loading minimal WP-CLI classes.
	 * @return array{exit_code: int, output: string}
	 */
	private function run_isolated( string $code ): array {
		$wp_cli_root = var_export( dirname( __DIR__ ), true );
		$bootstrap   = <<<PHP
define( 'WP_CLI_ROOT', {$wp_cli_root} );
define( 'WP_CLI_VENDOR_DIR', WP_CLI_ROOT . '/vendor' );
require_once WP_CLI_ROOT . '/php/class-wp-cli.php';
require_once WP_CLI_ROOT . '/php/WP_CLI/RequestsLibrary.php';
require_once WP_CLI_ROOT . '/php/WP_CLI/Bootstrap/BootstrapState.php';
require_once WP_CLI_ROOT . '/php/WP_CLI/Bootstrap/BootstrapStep.php';
require_once WP_CLI_ROOT . '/php/WP_CLI/Bootstrap/IncludeRequestsAutoloader.php';
{$code}
PHP;

		$cmd = escapeshellarg( PHP_BINARY ) . ' -r ' . escapeshellarg( $bootstrap ) . ' 2>&1';
		exec( $cmd, $lines, $exit_code );

		return [
			'exit_code' => $exit_code,
			'output'    => implode( "\n", $lines ),
		];
	}

	public function test_include_requests_autoloader_does_not_load_files_from_cwd(): void {
		$dir = $this->create_temp_dir();
		mkdir( $dir . '/wp-includes/Requests/src', 0777, true );
		file_put_contents( $dir . '/wp-load.php', "<?php\n" );
		file_put_contents( $dir . '/wp-includes/version.php', "<?php\n\$wp_version = '6.7';\n" );
		file_put_contents(
			$dir . '/wp-includes/Requests/src/Autoload.php',
			"<?php\nfile_put_contents( " . var_export( $dir . '/loaded-v2.txt', true ) . ", '1' );\n"
		);
		file_put_contents(
			$dir . '/wp-includes/class-requests.php',
			"<?php\nfile_put_contents( " . var_export( $dir . '/loaded-v1.txt', true ) . ", '1' );\n"
		);

		$dir_export = var_export( $dir, true );
		$result     = $this->run_isolated(
			<<<PHP
chdir( {$dir_export} );
\$step = new WP_CLI\Bootstrap\IncludeRequestsAutoloader();
\$step->process( new WP_CLI\Bootstrap\BootstrapState() );
echo json_encode( [
	'v2_loaded' => class_exists( '\\WpOrg\\Requests\\Autoload', false ),
	'v1_loaded' => class_exists( '\\Requests', false ),
] );
PHP
		);

		$this->assertSame( 0, $result['exit_code'], $result['output'] );
		$this->assertFileDoesNotExist( $dir . '/loaded-v2.txt' );
		$this->assertFileDoesNotExist( $dir . '/loaded-v1.txt' );

		/** @var array{v2_loaded: bool, v1_loaded: bool}|null $data */
		$data = json_decode( $result['output'], true );
		$this->assertIsArray( $data );
		$this->assertFalse( $data['v2_loaded'] );
		$this->assertFalse( $data['v1_loaded'] );
	}

	public function test_deferred_autoloader_loads_bundled_requests_on_demand(): void {
		$result = $this->run_isolated(
			<<<'PHP'
WP_CLI\RequestsLibrary::register_deferred_autoloader();
$before_autoload = class_exists( '\WpOrg\Requests\Autoload', false );
$has_transport   = interface_exists( '\WpOrg\Requests\Transport' );
$after_autoload  = class_exists( '\WpOrg\Requests\Autoload', false );
echo json_encode( [
	'before'        => $before_autoload,
	'has_transport' => $has_transport,
	'after'         => $after_autoload,
	'is_cli'        => WP_CLI\RequestsLibrary::is_cli(),
	'is_v2'         => WP_CLI\RequestsLibrary::is_v2(),
] );
PHP
		);

		$this->assertSame( 0, $result['exit_code'], $result['output'] );

		/** @var array{before: bool, has_transport: bool, after: bool, is_cli: bool, is_v2: bool}|null $data */
		$data = json_decode( $result['output'], true );
		$this->assertIsArray( $data );
		$this->assertFalse( $data['before'] );
		$this->assertTrue( $data['has_transport'] );
		$this->assertTrue( $data['after'] );
		$this->assertTrue( $data['is_cli'] );
		$this->assertTrue( $data['is_v2'] );
	}

	public function test_deferred_autoloader_ignores_unrelated_and_v1_classes(): void {
		$result = $this->run_isolated(
			<<<'PHP'
WP_CLI\RequestsLibrary::register_deferred_autoloader();
$unrelated = class_exists( 'WP_CLI_NonExistent_Test_Class' );
$v1        = class_exists( '\Requests' );
echo json_encode( [
	'unrelated' => $unrelated,
	'v1'        => $v1,
	'v2_loaded' => class_exists( '\WpOrg\Requests\Autoload', false ),
] );
PHP
		);

		$this->assertSame( 0, $result['exit_code'], $result['output'] );

		/** @var array{unrelated: bool, v1: bool, v2_loaded: bool}|null $data */
		$data = json_decode( $result['output'], true );
		$this->assertIsArray( $data );
		$this->assertFalse( $data['unrelated'] );
		$this->assertFalse( $data['v1'] );
		$this->assertFalse( $data['v2_loaded'] );
	}

	public function test_register_wp_core_autoloader_loads_v2_and_unregisters_deferred(): void {
		$dir = $this->create_temp_dir();
		mkdir( $dir . '/wp-includes/Requests/src', 0777, true );
		file_put_contents(
			$dir . '/wp-includes/Requests/src/Autoload.php',
			<<<'PHP'
<?php
namespace WpOrg\Requests;
class Autoload {
	public static $registered = false;
	public static function register() {
		self::$registered = true;
	}
}
PHP
		);

		$dir_export = var_export( $dir, true );
		$result     = $this->run_isolated(
			<<<PHP
WP_CLI\RequestsLibrary::register_deferred_autoloader();
\$loaded = WP_CLI\RequestsLibrary::register_wp_core_autoloader( {$dir_export} );
echo json_encode( [
	'loaded'     => \$loaded,
	'registered' => \WpOrg\Requests\Autoload::\$registered,
	'is_core'    => WP_CLI\RequestsLibrary::is_core(),
	'is_v2'      => WP_CLI\RequestsLibrary::is_v2(),
	'class_name' => WP_CLI\RequestsLibrary::get_class_name(),
] );
PHP
		);

		$this->assertSame( 0, $result['exit_code'], $result['output'] );

		/** @var array{loaded: bool, registered: bool, is_core: bool, is_v2: bool, class_name: string}|null $data */
		$data = json_decode( $result['output'], true );
		$this->assertIsArray( $data );
		$this->assertTrue( $data['loaded'] );
		$this->assertTrue( $data['registered'] );
		$this->assertTrue( $data['is_core'] );
		$this->assertTrue( $data['is_v2'] );
		$this->assertSame( '\WpOrg\Requests\Requests', $data['class_name'] );
	}

	public function test_register_wp_core_autoloader_loads_v1_and_unregisters_deferred(): void {
		$dir = $this->create_temp_dir();
		mkdir( $dir . '/wp-includes', 0777, true );
		file_put_contents(
			$dir . '/wp-includes/class-requests.php',
			<<<'PHP'
<?php
class Requests {
	public static $registered = false;
	public static function register_autoloader() {
		self::$registered = true;
	}
}
PHP
		);

		$dir_export = var_export( $dir, true );
		$result     = $this->run_isolated(
			<<<PHP
WP_CLI\RequestsLibrary::register_deferred_autoloader();
\$loaded = WP_CLI\RequestsLibrary::register_wp_core_autoloader( {$dir_export} );
echo json_encode( [
	'loaded'          => \$loaded,
	'registered'      => \Requests::\$registered,
	'is_core'         => WP_CLI\RequestsLibrary::is_core(),
	'is_v1'           => WP_CLI\RequestsLibrary::is_v1(),
	'class_name'      => WP_CLI\RequestsLibrary::get_class_name(),
	'v2_transport'    => interface_exists( '\WpOrg\Requests\Transport' ),
] );
PHP
		);

		$this->assertSame( 0, $result['exit_code'], $result['output'] );

		/** @var array{loaded: bool, registered: bool, is_core: bool, is_v1: bool, class_name: string, v2_transport: bool}|null $data */
		$data = json_decode( $result['output'], true );
		$this->assertIsArray( $data );
		$this->assertTrue( $data['loaded'] );
		$this->assertTrue( $data['registered'] );
		$this->assertTrue( $data['is_core'] );
		$this->assertTrue( $data['is_v1'] );
		$this->assertSame( '\Requests', $data['class_name'] );
		$this->assertFalse( $data['v2_transport'] );
	}

	public function test_register_wp_core_autoloader_returns_false_when_missing_and_bundled_fallback_works(): void {
		$dir        = $this->create_temp_dir();
		$dir_export = var_export( $dir, true );
		$result     = $this->run_isolated(
			<<<PHP
WP_CLI\RequestsLibrary::register_deferred_autoloader();
\$loaded = WP_CLI\RequestsLibrary::register_wp_core_autoloader( {$dir_export} );
if ( ! \$loaded ) {
	WP_CLI\RequestsLibrary::register_bundled_autoloader();
}
echo json_encode( [
	'loaded_core'  => \$loaded,
	'is_cli'       => WP_CLI\RequestsLibrary::is_cli(),
	'is_v2'        => WP_CLI\RequestsLibrary::is_v2(),
	'has_requests' => class_exists( '\WpOrg\Requests\Requests' ),
] );
PHP
		);

		$this->assertSame( 0, $result['exit_code'], $result['output'] );

		/** @var array{loaded_core: bool, is_cli: bool, is_v2: bool, has_requests: bool}|null $data */
		$data = json_decode( $result['output'], true );
		$this->assertIsArray( $data );
		$this->assertFalse( $data['loaded_core'] );
		$this->assertTrue( $data['is_cli'] );
		$this->assertTrue( $data['is_v2'] );
		$this->assertTrue( $data['has_requests'] );
	}
}
