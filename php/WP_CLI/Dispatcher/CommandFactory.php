<?php

namespace WP_CLI\Dispatcher;

/**
 * Creates CompositeCommand or Subcommand instances.
 *
 * @package WP_CLI
 */
class CommandFactory {

	/**
	 * Create a new CompositeCommand (or Subcommand if class has __invoke())
	 *
	 * @param string $name Represents how the command should be invoked
	 * @param string $class A subclass of WP_CLI_Command
	 * @param mixed $parent The new command's parent Composite (or Root) command
	 */
	public static function create( $name, $class, $parent ) {
		$reflection = new \ReflectionClass( $class );

		if ( $reflection->hasMethod( '__invoke' ) ) {
			$command = self::create_subcommand( $parent, $name, $reflection->name,
				$reflection->getMethod( '__invoke' ) );
		} else {
			$command = self::create_composite_command( $parent, $name, $reflection );
		}

		return $command;
	}

	/**
	 * Create a new Subcommand instance.
	 *
	 * @param mixed $parent The new command's parent Composite command
	 * @param string $name Represents how the command should be invoked
	 * @param string $class A subclass of WP_CLI_Command
	 * @param string $method Class method to be called upon invocation.
	 */
	private static function create_subcommand( $parent, $name, $class_name, $method ) {
		$docparser = new \WP_CLI\DocParser( $method->getDocComment() );

		if ( !$name )
			$name = $docparser->get_tag( 'subcommand' );

		if ( !$name )
			$name = $method->name;

		$method_name = $method->name;

		$when_invoked = function ( $args, $assoc_args ) use ( $class_name, $method_name ) {
			call_user_func( array( new $class_name, $method_name ), $args, $assoc_args );
		};

		return new Subcommand( $parent, $name, $docparser, $when_invoked );
	}

	/**
	 * Create a new Composite command instance.
	 *
	 * @param mixed $parent The new command's parent Root or Composite command
	 * @param string $name Represents how the command should be invoked
	 * @param ReflectionClass $reflection
	 */
	private static function create_composite_command( $parent, $name, $reflection ) {
		$docparser = new \WP_CLI\DocParser( $reflection->getDocComment() );

		$container = new CompositeCommand( $parent, $name, $docparser );

		foreach ( $reflection->getMethods() as $method ) {
			if ( !self::is_good_method( $method ) )
				continue;

			$subcommand = self::create_subcommand( $container, false, $reflection->name, $method );

			$subcommand_name = $subcommand->get_name();

			$container->add_subcommand( $subcommand_name, $subcommand );
		}

		return $container;
	}

	/**
	 * Check whether a method is actually callable.
	 *
	 * @param ReflectionMethod $method
	 * @return bool
	 */
	private static function is_good_method( $method ) {
		return $method->isPublic() && !$method->isStatic() && 0 !== strpos( $method->getName(), '__' );
	}
}

