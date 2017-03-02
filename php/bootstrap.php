<?php

namespace WP_CLI;

/**
 * Get the list of ordered steps that need to be processed to bootstrap WP-CLI.
 *
 * Each entry is a fully qualified class name for a class implementing the
 * `WP_CLI\Bootstrap\BootstrapStep` interface.
 *
 * @return string[]
 */
function get_bootstrap_steps() {
	return array(
		'WP_CLI\Bootstrap\LoadUtilityFunctions',
		'WP_CLI\Bootstrap\LoadDispatcher',
		'WP_CLI\Bootstrap\DeclareMainClass',
		'WP_CLI\Bootstrap\DeclareAbstractBaseCommand',
		'WP_CLI\Bootstrap\IncludeFrameworkAutoloader',
		'WP_CLI\Bootstrap\ConfigureRunner',
		'WP_CLI\Bootstrap\InitializeColorization',
		'WP_CLI\Bootstrap\InitializeLogger',
		'WP_CLI\Bootstrap\IncludePackageAutoloader',
		'WP_CLI\Bootstrap\IncludeBundledAutoloader',
		'WP_CLI\Bootstrap\IncludePHARAutoloader',
		'WP_CLI\Bootstrap\LoadRequiredCommand',
		'WP_CLI\Bootstrap\RegisterFrameworkCommands',
		'WP_CLI\Bootstrap\LaunchRunner',
	);
}

/**
 * Manually include the classes needed for the bootstrap process.
 *
 * The autoloader is not active at this point, so we need to manually traverse
 * the folder and include the files one by ones.
 */
function initialize_bootstrap() {
	$bootstrap_dir = WP_CLI_ROOT . '/php/WP_CLI/Bootstrap';

	$filenames = array_filter(
		scandir( $bootstrap_dir ),
		function ( $filename ) {
			return '.php' === substr( $filename, - 4 );
		}
	);

	// Make sure the interface and the base classes are loaded before the final
	// implementations.
	$filenames = array_unique( array_merge(
		array(
			'BootstrapStep.php',
			'RunnerInstance.php',
			'AutoloaderStep.php',
		),
		$filenames
	) );

	foreach ( $filenames as $filename ) {
		if ( '.php' !== substr( $filename, - 4 ) ) {
			continue;
		}

		include_once "$bootstrap_dir/$filename";
	}
}

/**
 * Process the bootstrapping steps.
 *
 * Loops over each of the provided steps, instantiates it and then calls its
 * `process()` method.
 */
function bootstrap() {
	initialize_bootstrap();

	foreach ( get_bootstrap_steps() as $step ) {
		/** @var \WP_CLI\Bootstrap\BootstrapStep $step_instance */
		$step_instance = new $step();
		$step_instance->process();
	}
}
