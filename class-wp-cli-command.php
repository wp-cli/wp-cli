<?php

/**
 * Base class for WP-CLI commands
 *
 * @package wp-cli
 * @author Andreas Creten
 */
abstract class WP_CLI_Command {

	/**
	 * Transfers the handling to the appropriate method
     *
     * @param array $args
	 * @param array $assoc_args
	 */
	public function __construct( $args, $assoc_args ) {
        $sub_command = array_shift( $args );

		if ( !method_exists( $this, $sub_command ) ) {
			// This if for reserved keywords in php (like list, isset)
			$sub_command = '_'.$sub_command;
		}

		if ( !method_exists( $this, $sub_command ) || isset( $assoc_args[ 'help' ] ) ) {
			$sub_command = 'help';
		}

		$this->$sub_command( $args, $assoc_args );
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
			if ( $method->isPublic() && !$method->isStatic() && !$method->isConstructor() ) {
				$name = $method->name;

				if ( strpos( $name, '_' ) === 0 ) {
					$name = substr( $method, 1 );
				}

				$methods[] = $name;
			}
        }

		return $methods;
    }
}
