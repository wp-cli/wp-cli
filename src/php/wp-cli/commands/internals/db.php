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

	protected $aliases = array( 'dump' => 'export' );

	/**
	 * Return a string for connecting to the DB.
	 */
	protected function connect_string() {
		return sprintf( 'mysql --host="%s" --database="%s" --user="%s" --password="%s"',
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
	 * Exports the WordPress DB as SQL using mysqldump.
	 */
	function export( $args, $assoc_args ) {
		$result_file = $this->get_file_name( $args );

		$exec = sprintf( 'mysqldump "%s" --user="%s" --password="%s" --host="%s" --result-file "%s"',
			DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, $result_file );

		exec( $exec );

		WP_CLI::success( sprintf( 'Exported to %s', $result_file ) );
	}

	/**
	 * Imports a database from a file.
	 */
	function import( $args, $assoc_args ) {
		$result_file = $this->get_file_name( $args );

		$exec = sprintf( 'mysql "%s" --user="%s" --password="%s" --host="%s" < "%s"',
			DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, $result_file );

		exec( $exec );

		WP_CLI::success( sprintf( 'Imported from %s', $result_file ) );
	}

	private function get_file_name( $args ) {
		if ( empty( $args ) )
			return sprintf( '%s.sql', DB_NAME );

		return $args[0];
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp db <sub-command> [<file>]

Available sub-commands:
   cli          Open a SQL command-line interface using the WordPress credentials.

   connect      Print a string for connecting to the database.

   export       Export the WordPress database using mysqldump.

   import       Import a database exported via mysqldump.

   query        Execute a query against the WordPress database.
EOB
	);
	}
}
