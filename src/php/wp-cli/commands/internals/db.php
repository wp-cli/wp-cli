<?php

WP_CLI::add_command( 'db', 'DB_Command' );

/**
 * Implement db command
 *
 * @package wp-cli
 * @subpackage commands/internals
 **/
class DB_Command extends WP_CLI_Command {

	protected $default_subcommand = 'cli';

	protected $aliases = array( 'dump' => 'export' );

	/**
	 * Creates the database according to the wp-config.php file
	 */
	function create() {
		exec( sprintf( 'mysql --host="%s" --user="%s" --password="%s" --execute="CREATE DATABASE %s"',
			DB_HOST, DB_USER, DB_PASSWORD, DB_NAME ) );
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

	/**
	 * Return a string for connecting to the DB.
	 */
	private function connect_string() {
		return sprintf( 'mysql --host="%s" --user="%s" --password="%s" --database="%s"',
			DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
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

   create       Create a database using the info from wp-config.php.
EOB
	);
	}
}
