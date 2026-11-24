<?php

namespace WP_CLI\Dispatcher;

use Composer\Autoload\ClassLoader;
use WP_CLI;
use WP_CLI\DocParser;

/**
 * Placeholder for a command registered via a class name, materialized on first use.
 *
 * Registering a command eagerly means autoloading its class and parsing the doc
 * comments of all its methods, even if a different command ends up being run.
 * A lazy command only records the class names registered for its name, plus any
 * subcommands attached to it in the meantime, and builds the real command tree
 * once it is looked up.
 *
 * @package WP_CLI
 */
class LazyCommand extends CompositeCommand {

	/**
	 * Class names registered for this command, in registration order.
	 *
	 * @var array<int, class-string>
	 */
	private $classes = [];

	/**
	 * Autoloaders that located each class when it was registered, keyed like `$classes`.
	 *
	 * @var array<int, ClassLoader>
	 */
	private $class_loaders = [];

	/**
	 * @param RootCommand|CompositeCommand $parent_command Parent command.
	 * @param string                       $name           Command name.
	 * @param class-string                 $class_name     Command class.
	 * @param ClassLoader|null             $class_loader   Autoloader that located the class when it was registered.
	 */
	public function __construct( $parent_command, $name, $class_name, $class_loader = null ) {
		parent::__construct( $parent_command, $name, new DocParser( '' ) );

		$this->add_class( $class_name, $class_loader );
	}

	/**
	 * Register another class for the same command name.
	 *
	 * @param class-string     $class_name   Command class.
	 * @param ClassLoader|null $class_loader Autoloader that located the class when it was registered.
	 * @return void
	 */
	public function add_class( $class_name, $class_loader = null ) {
		$this->classes[] = $class_name;

		if ( null !== $class_loader ) {
			$this->class_loaders[ count( $this->classes ) - 1 ] = $class_loader;
		}
	}

	/**
	 * Build the real command, replicating the merge rules of `WP_CLI::add_command()`.
	 *
	 * @return Subcommand|CompositeCommand
	 */
	public function materialize() {
		/** @var RootCommand|CompositeCommand $parent */
		$parent  = $this->parent;
		$current = null;

		foreach ( $this->classes as $index => $class_name ) {
			// Load the class from where an eager registration would have, so
			// that an autoloader registered in the meantime cannot provide a
			// different definition of it.
			if ( isset( $this->class_loaders[ $index ] ) && ! class_exists( $class_name, false ) ) {
				$this->class_loaders[ $index ]->loadClass( $class_name );
			}

			if ( ! class_exists( $class_name ) ) {
				WP_CLI::error( sprintf( 'Callable %s does not exist, and cannot be registered as `wp %s`.', (string) json_encode( $class_name ), implode( ' ', array_slice( get_path( $this ), 1 ) ) ) );
			}

			$command = CommandFactory::create( $this->name, $class_name, $parent );

			$command_type = $command instanceof CommandNamespace ? 'namespace' : 'command';
			WP_CLI::debug( "Adding {$command_type}: {$this->name} ({$class_name}, lazily)", 'commands' );

			if ( null !== $current && $command instanceof CommandNamespace ) {
				continue;
			}

			if ( $current instanceof CompositeCommand && $current->can_have_subcommands() ) {
				$keep = ( $command instanceof CommandNamespace || ! $command->can_have_subcommands() ) ? $current : $command;
				foreach ( $current->get_subcommands() as $subname => $subcommand ) {
					$keep->add_subcommand( $subname, $subcommand, false );
				}
				foreach ( $command->get_subcommands() as $subname => $subcommand ) {
					$keep->add_subcommand( $subname, $subcommand, true );
				}
			}

			$current = $command;
		}

		/** @var Subcommand|CompositeCommand $current */

		if ( ! empty( $this->subcommands ) ) {
			if ( ! $current->can_have_subcommands() ) {
				throw new \Exception(
					sprintf( "'%s' can't have subcommands.", implode( ' ', get_path( $current ) ) )
				);
			}

			foreach ( $this->subcommands as $subname => $subcommand ) {
				$subcommand->parent = $current;
				$current->add_subcommand( $subname, $subcommand );
			}
		}

		return $current;
	}
}
