<?php

WP_CLI::addCommand( 'db', 'DBCommand' );

/**
 * Implement db command
 *
 * @package wp-cli
 * @subpackage commands/internals
 **/
class DBCommand extends WP_CLI_Command {

	protected $default_subcommand = 'cli';

	/**
	 * Return a string for connecting to the DB.
	 */
	protected function connect_string() {
		return sprintf( 'mysql --host=%s --database=%s --user=%s --password=%s',
			DB_HOST, DB_NAME, DB_USER, DB_PASSWORD );
	}

	/**
	 * Print a string for connecting to the DB.
	 */
	function connect() {
		WP_CLI::line( $this->connect_string() );
	}

	/**
	 * Open a SQL command-line interface using WordPress's credentials.
	 */
	function cli() {
		proc_close( proc_open( $this->connect_string() , array( 0 => STDIN, 1 => STDOUT, 2 => STDERR ), $pipes ) );
	}

	/**
	 * Exports the WordPress DB as SQL using mysqldump.
	 */
	function dump( $args, $assoc_args ) {
		if ( !isset( $assoc_args['file'] ) ) {
			$result_file = sprintf( '%s.sql', DB_NAME );
		} else {
			$result_file = $assoc_args['file'];
		}

		$exec = sprintf( 'mysqldump %s --user=%s --password=%s --host=%s --result-file %s',
			DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, $result_file );

		exec( $exec );

		WP_CLI::success( sprintf( 'Dumped to %s', $result_file ) );
	}

	/**
	 * Execute a query against the site database.
	 */
	function query( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp sql query <SQL>" );
			exit;
		}

		$query = $args[0];

		$exec = $this->connect_string();
		$exec .= sprintf(' --execute="%s"', $query);

		$result = exec( $exec );
		WP_CLI::line( $result );
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
wp db cli               Open a SQL command-line interface using the WordPress credentials.
wp db connect           Print a string for connecting to the database.
wp db dump              Exports the WordPress database using mysqldump.
wp db query             Execute a query against the WordPress database.
EOB
	);
	}
}
