<?php

WP_CLI::addCommand( 'sql', 'SqlCommand' );

/**
 * Implement sql command
 *
 * @package wp-cli
 * @subpackage commands/internals
 **/
class SqlCommand extends WP_CLI_Command {

	protected $default_subcommand = 'cli';

	/**
	 * return a string to connecting to the DB.
	 *
	 * @param void
	 * @return string $connect
	 */
	protected function connect_string() {
		$connect = sprintf( 'mysql --database=%s --user=%s --password=%s',
			DB_NAME, DB_USER, DB_PASSWORD);
		return $connect;
	}

	/**
	 * A string for connecting to the DB.
	 *
	 * @param string $args
	 * @return void
	 */
	function connect( $args = array() ) {
		$connect = $this->connect_string();
		WP_CLI::line( $connect );
	}

	/**
	 * Open a SQL command-line interface using WordPress's credentials.
	 * @param string $args
	 * @return void
	 */
	function cli() {
		$exec = $this->connect_string();

		proc_close( proc_open( $exec , array( 0 => STDIN, 1 => STDOUT, 2 => STDERR ), $pipes ) );
	}

	/**
	 * Exports the WordPress DB as SQL using mysqldump or equivalent.
	 * @param string $args
	 * @return void
	 */
	function dump( $args, $assoc_args ) {
		if ( !isset( $assoc_args['file'] ) ) {
			$result_file = sprintf( '%s.sql', DB_NAME );
		} else {
			$result_file = $assoc_args['file'];
		}

		$exec = sprintf( 'mysqldump %s --result-file %s --user=%s --password=%s', DB_NAME, $result_file, DB_USER, DB_PASSWORD );

		exec( $exec );
	}

	/**
	 * Execute a query against the site database.
	 * @param string $args
	 * @return void
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
wp sql cli               Open a SQL command-line interface using the WordPress credentials.
wp sql connect           A string for connecting to the database.
wp sql dump              Exports the WordPress database as SQL using mysqldump or equivalent.
wp sql query             Execute a query against the WordPress database.
EOB
		);
	}
}
