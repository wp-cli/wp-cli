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
	 * @synopsis
	 */
	function create( $_, $assoc_args ) {
		self::run_query( sprintf( 'CREATE DATABASE `%s`', DB_NAME ) );

		WP_CLI::success( "Database created." );
	}

	/**
	 * Delete the database.
	 *
	 * @synopsis [--yes]
	 */
	function drop( $_, $assoc_args ) {
		WP_CLI::confirm( "Are you sure you want to drop the database?", $assoc_args );

		self::run_query( sprintf( 'DROP DATABASE `%s`', DB_NAME ) );

		WP_CLI::success( "Database dropped." );
	}

	/**
	 * Remove all tables from the database.
	 *
	 * @synopsis [--yes]
	 */
	function reset( $_, $assoc_args ) {
		WP_CLI::confirm( "Are you sure you want to reset the database?", $assoc_args );

		self::run_query( sprintf( 'DROP DATABASE IF EXISTS `%s`', DB_NAME ) );
		self::run_query( sprintf( 'CREATE DATABASE `%s`', DB_NAME ) );

		WP_CLI::success( "Database reset." );
	}

	/**
	 * Optimize the database.
	 *
	 * @synopsis
	 */
	function optimize() {
		self::run( \WP_CLI\Utils\create_cmd(
			'mysqlcheck --optimize --host=%s --user=%s %s',
			DB_HOST, DB_USER, DB_NAME
		) );

		WP_CLI::success( "Database optimized." );
	}

	/**
	 * Repair the database.
	 *
	 * @synopsis
	 */
	function repair() {
		self::run( \WP_CLI\Utils\create_cmd(
			'mysqlcheck --repair --host=%s --user=%s %s',
			DB_HOST, DB_USER, DB_NAME ) );

		WP_CLI::success( "Database repaired." );
	}

	/**
	 * Open a mysql console using the WordPress credentials.
	 *
	 * @alias cli
	 *
	 * @synopsis
	 */
	function connect() {
		self::run( \WP_CLI\Utils\create_cmd(
			'mysql --host=%s --user=%s --database=%s',
			DB_HOST, DB_USER, DB_NAME ) );
	}

	/**
	 * Execute a query against the database.
	 *
	 * @synopsis <sql>
	 */
	function query( $args ) {
		list( $query ) = $args;

		self::run_query( $query );
	}

	/**
	 * Exports the database using mysqldump.
	 *
	 * @alias dump
	 * @synopsis [<file>]
	 */
	function export( $args, $assoc_args ) {
		$result_file = $this->get_file_name( $args );

		self::run( \WP_CLI\Utils\create_cmd(
			'mysqldump %s --user=%s --host=%s --result-file %s',
			DB_NAME, DB_USER, DB_HOST, $result_file ) );

		WP_CLI::success( sprintf( 'Exported to %s', $result_file ) );
	}

	/**
	 * Import database from a file.
	 *
	 * @synopsis [<file>]
	 */
	function import( $args, $assoc_args ) {
		$result_file = $this->get_file_name( $args );

		self::run( \WP_CLI\Utils\create_cmd(
			'mysql %s --user=%s --host=%s < %s',
			DB_NAME, DB_USER, DB_HOST, $result_file ) );

		WP_CLI::success( sprintf( 'Imported from %s', $result_file ) );
	}

	private function get_file_name( $args ) {
		if ( empty( $args ) )
			return sprintf( '%s.sql', DB_NAME );

		return $args[0];
	}

	private static function run_query( $query ) {
		return \WP_CLI\Utils\run_mysql_query( $query, array(
			'host' => DB_HOST,
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
		) );
	}

	private static function run( $cmd ) {
		$old_val = getenv( 'MYSQL_PWD' );

		putenv( 'MYSQL_PWD=' . DB_PASSWORD );
		WP_CLI::launch( $cmd );
		putenv( 'MYSQL_PWD=' . $old_val );
	}
}

WP_CLI::add_command( 'db', 'DB_Command' );
