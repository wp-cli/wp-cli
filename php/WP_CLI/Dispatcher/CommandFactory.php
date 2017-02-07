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
	 * @param string $callable A subclass of WP_CLI_Command, a function, or a closure
	 * @param mixed $parent The new command's parent Composite (or Root) command
	 */
	public static function create( $name, $callable, $parent ) {

		if ( ( is_object( $callable ) && ( $callable instanceof \Closure ) )
			|| ( is_string( $callable ) && function_exists( $callable ) ) ) {
			$reflection = new \ReflectionFunction( $callable );
			$command = self::create_subcommand( $parent, $name, $callable, $reflection );
		} else if ( is_array( $callable ) && is_callable( $callable ) ) {
			$reflection = new \ReflectionClass( $callable[0] );
			$command = self::create_subcommand( $parent, $name, array( $callable[0], $callable[1] ),
					$reflection->getMethod( $callable[1] ) );
		} else {
			$reflection = new \ReflectionClass( $callable );
			if ( $reflection->hasMethod( '__invoke' ) ) {
				$class = is_object( $callable ) ? $callable : $reflection->name;
				$command = self::create_subcommand( $parent, $name, array( $class, '__invoke' ),
					$reflection->getMethod( '__invoke' ) );
			} else {
				$command = self::create_composite_command( $parent, $name, $callable );
			}
		}

		return $command;
	}

	/**
	 * Create a new Subcommand instance.
	 *
	 * @param mixed $parent The new command's parent Composite command
	 * @param string $name Represents how the command should be invoked
	 * @param mixed $callable A callable function or closure, or class name and method
	 * @param object $reflection Reflection instance, for doc parsing
	 * @param string $class A subclass of WP_CLI_Command
	 * @param string $method Class method to be called upon invocation.
	 */
	private static function create_subcommand( $parent, $name, $callable, $reflection ) {
		$docparser = new \WP_CLI\DocParser( $reflection->getDocComment() );

		if ( is_array( $callable ) ) {
			if ( !$name )
				$name = $docparser->get_tag( 'subcommand' );

			if ( !$name )
				$name = $reflection->name;
		}

		$when_invoked = function ( $args, $assoc_args ) use ( $callable ) {
			if ( is_array( $callable ) ) {
				$callable[0] = is_object( $callable[0] ) ? $callable[0] : new $callable[0];
				call_user_func( array( $callable[0], $callable[1] ), $args, $assoc_args );
			} else {
				call_user_func( $callable, $args, $assoc_args );
			}
		};

		return new Subcommand( $parent, $name, $docparser, $when_invoked );
	}

	/**
	 * Create a new Composite command instance.
	 *
	 * @param mixed $parent The new command's parent Root or Composite command
	 * @param string $name Represents how the command should be invoked
	 * @param mixed $callable
	 */
	private static function create_composite_command( $parent, $name, $callable ) {
		$reflection = new \ReflectionClass( $callable );
		$docparser = new \WP_CLI\DocParser( $reflection->getDocComment() );

		$container = new CompositeCommand( $parent, $name, $docparser );

		foreach ( $reflection->getMethods() as $method ) {
			if ( !self::is_good_method( $method ) )
				continue;

			$class = is_object( $callable ) ? $callable : $reflection->name;
			$subcommand = self::create_subcommand( $container, false, array( $class, $method->name ), $method );

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

