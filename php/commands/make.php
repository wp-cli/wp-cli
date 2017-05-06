<?php

/**
 * Run a wp-cli script
 *
 * @package wp-cli
 */
class Make_Command extends WP_CLI_Command {

    /**
     * Run a series of commands from a .wpc script
   	 *
     * ## EXAMPLES
   	 *
   	 *     wp make my-build-script.wpc
   	 *
   	 * @synopsis <path>
     */
    public function __invoke( $args, $assoc_args )
    {
        foreach ( $args as $script ) {
            if ( !file_exists( $script ) ) {
                WP_CLI::error( "'$script' does not exist." );
            } else {
                $decoded = json_decode(file_get_contents($script));
                $this->_processScript($decoded);
            }
        }
    }

    protected function _processScript($script)
    {
        foreach( $script as $subCommand => $args) {
            $command = self::find_subcommand( array($subCommand) );

            if ( $command ) {
                continue;
            }

            // WordPress is already loaded, so there's no chance we'll find the command
            if ( function_exists( 'add_filter' ) ) {
                \WP_CLI::error( sprintf( "'%s' is not a registered wp command.", $subCommand ) );
            }
        }

        // all fine, time to run the show
        foreach ( $script as $subCommand ) {
            self::run_command($subCommand);
        }

    }

    private static function run_command($subCommand) {
        echo "Go gadget go!";
    }

    private static function find_subcommand( $args ) {
   		$command = \WP_CLI::get_root_command();

   		while ( !empty( $args ) && $command && $command->has_subcommands() ) {
   			$command = $command->find_subcommand( $args );
   		}

   		return $command;
   	}

}


WP_CLI::add_command( 'make', new Make_Command );