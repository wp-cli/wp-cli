<?php

/**
 * Base class for WP-CLI commands
 *
 * @package wp-cli
 */
abstract class WP_CLI_Command {

	protected $default_subcommand = 'help';

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

		if ( !method_exists( $this, $subcommand ) ) {
			// This if for reserved keywords in php (like list, isset)
			$subcommand = '_'.$subcommand;
		}

		if ( !method_exists( $this, $subcommand ) || isset( $assoc_args[ 'help' ] ) ) {
			$subcommand = 'help';
		}

		$this->$subcommand( $args, $assoc_args );
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
