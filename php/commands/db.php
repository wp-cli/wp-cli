<?php

/**
 * Perform basic database operations.
 *
 * @package wp-cli
 */
class DB_Command extends WP_CLI_Command {

	/**
	 * Create the database, as specified in wp-config.php
	 *
	 * @synopsis [--str]
	 */
	function create( $_, $assoc_args ) {
		self::run( $assoc_args, self::create_execute_cmd(
			sprintf( 'CREATE DATABASE `%s`', DB_NAME )
		) );

		WP_CLI::success( "Database created." );
	}

	/**
	 * Delete the database.
	 *
	 * @synopsis [--yes] [--str]
	 */
	function drop( $_, $assoc_args ) {
		$command = self::create_execute_cmd( sprintf( 'DROP DATABASE `%s`', DB_NAME ) );

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
		$drop_cmd = self::create_execute_cmd( sprintf( 'DROP DATABASE IF EXISTS `%s`', DB_NAME ) );

		$create_cmd = self::create_execute_cmd( sprintf( 'CREATE DATABASE `%s`', DB_NAME ) );

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
		self::run( $assoc_args, \WP_CLI\Utils\create_cmd(
			'mysqlcheck --optimize --host=%s --user=%s %s',
			DB_HOST, DB_USER, DB_NAME
		) );

		WP_CLI::success( "Database optimized." );
	}

	/**
	 * Repair the database.
	 *
	 * @synopsis [--str]
	 */
	function repair( $_, $assoc_args ) {
		self::run( $assoc_args, \WP_CLI\Utils\create_cmd(
			'mysqlcheck --repair --host=%s --user=%s %s',
			DB_HOST, DB_USER, DB_NAME
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

		self::run( $assoc_args, $this->connect_string() . \WP_CLI\Utils\create_cmd(
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

		self::run( $assoc_args, \WP_CLI\Utils\create_cmd(
			'mysqldump %s --user=%s --host=%s --result-file %s',
			DB_NAME, DB_USER, DB_HOST, $result_file ) );

		WP_CLI::success( sprintf( 'Exported to %s', $result_file ) );
	}

	/**
	 * Import database from a file.
	 *
	 * @synopsis [<file>] [--str]
	 */
	function import( $args, $assoc_args ) {
		$result_file = $this->get_file_name( $args );

		self::run( $assoc_args, \WP_CLI\Utils\create_cmd(
			'mysql %s --user=%s --host=%s < %s',
			DB_NAME, DB_USER, DB_HOST, $result_file ) );

		WP_CLI::success( sprintf( 'Imported from %s', $result_file ) );
	}

	private function connect_string() {
		return \WP_CLI\Utils\create_cmd( 'mysql --host=%s --user=%s --database=%s',
			DB_HOST, DB_USER, DB_NAME );
	}

	private function get_file_name( $args ) {
		if ( empty( $args ) )
			return sprintf( '%s.sql', DB_NAME );

		return $args[0];
	}

	private static function create_execute_cmd( $execute_statement ) {
		return \WP_CLI\Utils\create_cmd(
			'mysql --host=%s --user=%s --execute=%s',
			DB_HOST, DB_USER, $execute_statement
		);
	}

	private static function run( $assoc_args, $cmd ) {
		if ( isset( $assoc_args['str'] ) ) {
			WP_CLI::line( $cmd );
			exit;
		}

		$old_val = getenv( 'MYSQL_PWD' );

		putenv( 'MYSQL_PWD=' . DB_PASSWORD );
		WP_CLI::launch( $cmd );
		putenv( 'MYSQL_PWD=' . $old_val );
	}
}

WP_CLI::add_command( 'db', 'DB_Command' );
