<?php

namespace WP_CLI;

use WP_CLI\Bootstrap\BootstrapState;
use WP_CLI\Bootstrap\BootstrapStep;

/**
 * Get the list of ordered steps that need to be processed to bootstrap WP-CLI.
 *
 * Each entry is a fully qualified class name for a class implementing the
 * `WP_CLI\Bootstrap\BootstrapStep` interface.
 *
 * @return string[]
 */
function get_bootstrap_steps() {
	return [
		Bootstrap\DeclareFallbackFunctions::class,
		Bootstrap\LoadUtilityFunctions::class,
		Bootstrap\LoadDispatcher::class,
		Bootstrap\DeclareMainClass::class,
		Bootstrap\DeclareAbstractBaseCommand::class,
		Bootstrap\IncludeFrameworkAutoloader::class,
		Bootstrap\ConfigureRunner::class,
		Bootstrap\IncludeRequestsAutoloader::class,
		Bootstrap\InitializeColorization::class,
		Bootstrap\InitializeLogger::class,
		Bootstrap\DefineProtectedCommands::class,
		Bootstrap\LoadExecCommand::class,
		Bootstrap\LoadRequiredCommand::class,
		Bootstrap\IncludePackageAutoloader::class,
		Bootstrap\IncludeFallbackAutoloader::class,
		Bootstrap\RegisterFrameworkCommands::class,
		Bootstrap\RegisterDeferredCommands::class,
		Bootstrap\InitializeContexts::class,
		Bootstrap\LaunchRunner::class,
	];
}

/**
 * Register the classes needed for the bootstrap process.
 *
 * The Composer autoloader is not active yet at this point, so we need to use a
 * custom autoloader to fetch the bootstrap classes in a flexible way.
 */
function prepare_bootstrap() {
	require_once WP_CLI_ROOT . '/php/WP_CLI/Autoloader.php';

	$autoloader = new Autoloader();

	$autoloader->add_namespace(
		'WP_CLI\Bootstrap',
		WP_CLI_ROOT . '/php/WP_CLI/Bootstrap'
	)->register();
}

/**
 * Initialize and return the bootstrap state to pass from step to step.
 *
 * @return BootstrapState
 */
function initialize_bootstrap_state() {
	return new BootstrapState();
}

/**
 * Process the bootstrapping steps.
 *
 * Loops over each of the provided steps, instantiates it and then calls its
 * `process()` method.
 */
function bootstrap() {
	prepare_bootstrap();
	$state = initialize_bootstrap_state();

	foreach ( get_bootstrap_steps() as $step ) {
		/** @var BootstrapStep $step_instance */
		if ( class_exists( 'WP_CLI' ) ) {
			\WP_CLI::debug( "Processing bootstrap step: {$step}", 'bootstrap' );
		}

		$step_instance = new $step();
		$state         = $step_instance->process( $state );
	}
}
