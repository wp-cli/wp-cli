<?php
namespace WP_CLI\Help;

\WP_CLI::add_command( 'help', function( $args ) {
	if ( empty( $args ) ) {
		general_help();
		return;
	}

	maybe_load_man_page( $args );

	show_available_subcommands( $args[0] );
} );

function maybe_load_man_page( $args ) {
	$man_dir = WP_CLI_ROOT . "../../../man/";

	if ( !is_dir( $man_dir ) ) {
		\WP_CLI::warning( "man pages do not seem to be installed." );
	} else {
		$man_file = $man_dir . implode( '-', $args ) . '.1';

		if ( is_readable( $man_file ) ) {
			exit( \WP_CLI::launch( "man $man_file" ) );
		}
	}
}

function show_available_subcommands( $command ) {
	$class = \WP_CLI::load_command( $command );
	\WP_CLI\Dispatcher\describe_command( $class, $command );
}

function general_help() {
	\WP_CLI::line( 'Available commands:' );
	foreach ( \WP_CLI::load_all_commands() as $command => $class ) {
		if ( 'help' == $command )
			continue;

		$out = "    wp $command";

		$methods = \WP_CLI\Dispatcher\get_subcommands( $class );

		if ( !empty( $methods ) ) {
			$out .= ' [' . implode( '|', $methods ) . ']';
		}

		\WP_CLI::line( $out );
	}

	\WP_CLI::line(<<<EOB

See 'wp help <command>' for more information on a specific command.

Global parameters:
--user=<id|login>   set the current user
--url=<url>         set the current URL
--path=<path>       set the current path to the WP install
--require=<path>    load a certain file before running the command
--quiet             suppress informational messages
--version           print wp-cli version
EOB
	);
}

