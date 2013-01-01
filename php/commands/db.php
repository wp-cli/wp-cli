<?php

/**
 * Perform basic database operations.
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class DB_Command extends WP_CLI_Command {

	/**
	 * Create the database, as specified in wp-config.php
	 *
	 * @synopsis [--str]
	 */
	function create( $_, $assoc_args ) {
		self::run( $assoc_args, self::create_cmd(
			'mysql --host=%s --user=%s --password=%s --execute=%s',
			DB_HOST, DB_USER, DB_PASSWORD, 'CREATE DATABASE ' . DB_NAME
		) );

		WP_CLI::success( "Database created." );
	}

	/**
	 * Delete the database.
	 *
	 * @synopsis [--yes] [--str]
	 */
	function drop( $_, $assoc_args ) {
		$command = self::create_cmd(
			'mysql --host=%s --user=%s --password=%s --execute=%s',
			DB_HOST, DB_USER, DB_PASSWORD, 'DROP DATABASE ' . DB_NAME
		);

		if ( !isset( $assoc_args['str'] ) ) {
			WP_CLI::confirm( "Are you sure you want to drop the database?", $assoc_args );
			WP_CLI::launch( $command );
			WP_CLI::success( "Database dropped." );
		} else {
			WP_CLI::line( $command );
		}
	}

	/**
	 * Remove all tables from the database.
	 *
	 * @synopsis [--yes] [--str]
	 */
	function reset( $_, $assoc_args ) {
		$drop_cmd = self::create_cmd(
			'mysql --host=%s --user=%s --password=%s --execute=%s',
			DB_HOST, DB_USER, DB_PASSWORD, 'DROP DATABASE IF EXISTS ' . DB_NAME
		);

		$create_cmd = self::create_cmd(
			'mysql --host=%s --user=%s --password=%s --execute=%s',
			DB_HOST, DB_USER, DB_PASSWORD, 'CREATE DATABASE ' . DB_NAME
		);

		if ( !isset( $assoc_args['str'] ) ) {
			WP_CLI::confirm( "Are you sure you want to reset the database?", $assoc_args );
			WP_CLI::launch( $drop_cmd );
			WP_CLI::launch( $create_cmd );
			WP_CLI::success( "Database reset." );
		} else {
			WP_CLI::line( $drop_cmd );
			WP_CLI::line( $create_cmd );
		}
	}

	/**
	 * Optimize the database.
	 *
	 * @synopsis [--str]
	 */
	function optimize( $_, $assoc_args ) {
		self::run( $assoc_args, self::create_cmd(
			'mysqlcheck --optimize --host=%s --user=%s --password=%s %s',
			DB_HOST, DB_USER, DB_PASSWORD, DB_NAME
		) );

		WP_CLI::success( "Database optimized." );
	}

	/**
	 * Repair the database.
	 *
	 * @synopsis [--str]
	 */
	function repair( $_, $assoc_args ) {
		self::run( $assoc_args, self::create_cmd(
			'mysqlcheck --repair --host=%s --user=%s --password=%s %s',
			DB_HOST, DB_USER, DB_PASSWORD, DB_NAME
		) );

		WP_CLI::success( "Database repaired." );
	}

	/**
	 * Open a mysql console using the WordPress credentials.
	 *
	 * @alias cli
	 *
	 * @synopsis [--str]
	 */
	function connect( $_, $assoc_args ) {
		self::run( $assoc_args, $this->connect_string() );
	}

	/**
	 * Execute a query against the database.
	 *
	 * @synopsis <sql> [--str]
	 */
	function query( $args, $assoc_args ) {
		list( $query ) = $args;

		self::run( $assoc_args, $this->connect_string() . self::create_cmd(
			' --execute=%s', $query ) );
	}

	/**
	 * Exports the database using mysqldump.
	 *
	 * @alias dump
	 * @synopsis [<file>] [--str]
	 */
	function export( $args, $assoc_args ) {
		$result_file = $this->get_file_name( $args );

		self::run( $assoc_args, self::create_cmd(
			'mysqldump %s --user=%s --password=%s --host=%s --result-file %s',
			DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, $result_file ) );

		WP_CLI::success( sprintf( 'Exported to %s', $result_file ) );
	}

	/**
	 * Import database from a file.
	 *
	 * @synopsis [<file>] [--str]
	 */
	function import( $args, $assoc_args ) {
		$result_file = $this->get_file_name( $args );

		self::run( $assoc_args, self::create_cmd(
			'mysql %s --user=%s --password=%s --host=%s < %s',
			DB_NAME, DB_USER, DB_PASSWORD, DB_HOST, $result_file ) );

		WP_CLI::success( sprintf( 'Imported from %s', $result_file ) );
	}

	private function connect_string() {
		return self::create_cmd( 'mysql --host=%s --user=%s --password=%s --database=%s',
			DB_HOST, DB_USER, DB_PASSWORD, DB_NAME );
	}

	private function get_file_name( $args ) {
		if ( empty( $args ) )
			return sprintf( '%s.sql', DB_NAME );

		return $args[0];
	}

	/**
	 * Given a formatted string and an arbitrary number of arguments,
	 * returns the final command, with the parameters escaped
	 */
	private static function create_cmd( $cmd ) {
		$args = func_get_args();

		$cmd = array_shift( $args );

		return vsprintf( $cmd, array_map( 'escapeshellarg', $args ) );
	}

	private static function run( $assoc_args, $cmd ) {
		if ( isset( $assoc_args['str'] ) ) {
			WP_CLI::line( $cmd );
			exit;
		}

		WP_CLI::launch( $cmd );
	}
}

WP_CLI::add_command( 'db', 'DB_Command' );

