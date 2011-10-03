<?php

/**
 * Base class for WP-CLI commands
 *
 * @package wp-cli
 * @author Andreas Creten
 */
abstract class WP_CLI_Command {

	/**
	 * Return a short description for the command.
	 *
	 * @return string
	 */
	public static function get_description() {
		return false;
	}

	/**
	 * Keeps a reference to the current command name
	 *
	 * @param string
	 */
	protected $command;

    /**
     * Construct for this class, transfers the cli arguments to the right class
     *
	 * @param string $command
     * @param array $args
     * @param array $assoc_args
     */
    function __construct( $command, $args, $assoc_args ) {
		$this->command = $command;

		$this->dispatch( $args, $assoc_args );
	}

	/**
	 * Transfers the handling to the appropriate method
     *
     * @param array $args
	 * @param array $assoc_args
	 */
	protected function dispatch( $args, $assoc_args ) {
        // The first command is the sub command
        $sub_command = array_shift($args);

		// Assosciative arguments keys
		$assoc_keys = array_keys($assoc_args);

		if ( !method_exists($this, $sub_command) ) {
			// This if for reserved keywords in php (like list, isset)
			$sub_command = '_'.$sub_command;
		}

		if ( !method_exists($this, $sub_command) || in_array( 'help', $assoc_keys ) ) {
			$sub_command = 'help';
		}
		
		$this->$sub_command($args, $assoc_args);
    }

    /**
     * General help function for this command
     *
     * @param string $args
     * @param string $assoc_args
     * @return void
     */
    public function help( $args = array(), $assoc_args = array() ) {
		$desc = $this->get_description();
		if ( $desc ) {
			WP_CLI::line( $desc );
			WP_CLI::line();
		}

        // Show the list of sub-commands for this command
        WP_CLI::line( 'Example usage:' );
        WP_CLI::out( '    wp '.$this->command );

        $methods = WP_CLI_Command::get_methods($this);
        if ( !empty( $methods ) ) {
            WP_CLI::out(' ['.implode('|', $methods).']');
        }
        WP_CLI::line(' ...');
        WP_CLI::line();
    }

    /**
     * Get the filtered list of methods for a class.
     *
     * @param string $class
     * @return array The list of methods
     */
    static function get_methods($class) {
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
