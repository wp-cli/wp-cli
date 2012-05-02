<?php

/**
 * Base class for WP-CLI commands
 *
 * @package wp-cli
 */
abstract class WP_CLI_Command {

	protected $default_subcommand;

	protected $aliases = array();

	/**
	 * Transfers the handling to the appropriate method
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	public function __construct( $args, $assoc_args ) {
		if ( empty( $args ) )
			$subcommand = $this->default_subcommand;
		else
			$subcommand = array_shift( $args );

		if ( isset( $this->aliases[ $subcommand ] ) )
			$subcommand = $this->aliases[ $subcommand ];

		if ( !method_exists( $this, $subcommand ) ) {
			// This if for reserved keywords in php (like list, isset)
			$subcommand = '_' . $subcommand;
		}

		if ( __FUNCTION__ == $subcommand || !method_exists( $this, $subcommand ) ) {
			self::describe_command( get_class( $this ), WP_CLI_COMMAND );
		} else {
			$this->$subcommand( $args, $assoc_args );
		}
	}

	static function describe_command( $class, $command ) {
		if ( method_exists( $class, 'help' ) ) {
			$class::help();
			return;
		}

		$methods = self::get_subcommands( $class );

		$out = "usage: wp $command";

		if ( empty( $methods ) ) {
			WP_CLI::line( $out );
		} else {
			$out .= ' [' . implode( '|', $methods ) . ']';

			WP_CLI::line( $out );

			WP_CLI::line();
			WP_CLI::line( "See 'wp help $command <subcommand>' for more information on a specific subcommand." );
		}
	}

	/**
	 * Get the list of subcommands for a class.
	 *
	 * @param string $class
	 * @return array The list of methods
	 */
	static function get_subcommands( $class ) {
		$reflection = new ReflectionClass( $class );

		$methods = array();

		foreach ( $reflection->getMethods() as $method ) {
			if ( !$method->isPublic() || $method->isStatic() || $method->isConstructor() )
				continue;

			$name = $method->name;

			if ( strpos( $name, '_' ) === 0 ) {
				$name = substr( $name, 1 );
			}

			$methods[] = $name;
		}

		return $methods;
	}
}

