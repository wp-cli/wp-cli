<?php

use \WP_CLI\Utils;

/**
 * Perform basic database operations.
 */
class DB_Command extends WP_CLI_Command {

	/**
	 * Create the database, as specified in wp-config.php
	 */
	function create( $_, $assoc_args ) {

		self::run_query( self::get_create_query() );

		WP_CLI::success( "Database created." );
	}

	/**
	 * Delete the database.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 */
	function drop( $_, $assoc_args ) {
		WP_CLI::confirm( "Are you sure you want to drop the database?", $assoc_args );

		self::run_query( sprintf( 'DROP DATABASE `%s`', DB_NAME ) );

		WP_CLI::success( "Database dropped." );
	}

	/**
	 * Remove all tables from the database.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 */
	function reset( $_, $assoc_args ) {
		WP_CLI::confirm( "Are you sure you want to reset the database?", $assoc_args );

		self::run_query( sprintf( 'DROP DATABASE IF EXISTS `%s`', DB_NAME ) );
		self::run_query( self::get_create_query() );

		WP_CLI::success( "Database reset." );
	}

	/**
	 * Optimize the database.
	 */
	function optimize() {
		self::run( Utils\esc_cmd( 'mysqlcheck --no-defaults %s', DB_NAME ), array(
			'optimize' => true,
		) );

		WP_CLI::success( "Database optimized." );
	}

	/**
	 * Repair the database.
	 */
	function repair() {
		self::run( Utils\esc_cmd( 'mysqlcheck --no-defaults %s', DB_NAME ), array(
			'repair' => true,
		) );

		WP_CLI::success( "Database repaired." );
	}

	/**
	 * Open a mysql console using the WordPress credentials.
	 *
	 * @alias connect
	 */
	function cli() {
		self::run( 'mysql --no-defaults --no-auto-rehash', array(
			'database' => DB_NAME
		) );
	}

	/**
	 * Execute a query against the database.
	 *
	 * ## OPTIONS
	 *
	 * [<sql>]
	 * : A SQL query. If not passed, will try to read from STDIN.
	 *
	 * ## EXAMPLES
	 *
	 *     # execute a query stored in a file
	 *     wp db query < debug.sql
	 *
	 *     # check all tables in the database
	 *     wp db query "CHECK TABLE $(wp db tables | paste -s -d',');"
	 */
	function query( $args ) {
		$assoc_args = array(
			'database' => DB_NAME
		);

		// The query might come from STDIN
		if ( !empty( $args ) ) {
			$assoc_args['execute'] = $args[0];
		}

		self::run( 'mysql --no-defaults --no-auto-rehash', $assoc_args );
	}

	/**
	 * Exports the database to a file or to STDOUT.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : The name of the SQL file to export. If '-', then outputs to STDOUT. If omitted, it will be '{dbname}.sql'.
	 *
	 * [--<field>=<value>]
	 * : Extra arguments to pass to mysqldump
	 *
	 * [--tables=<tables>]
	 * : The comma separated list of specific tables to export. Excluding this parameter will export all tables in the database.
	 *
	 * ## EXAMPLES
	 *
	 *     wp db export --add-drop-table
	 *     wp db export --tables=wp_options,wp_users
	 *
	 * @alias dump
	 */
	function export( $args, $assoc_args ) {
		$result_file = $this->get_file_name( $args );
		$stdout = ( '-' === $result_file );

		if ( ! $stdout ) {
			$assoc_args['result-file'] = $result_file;
		}

		$command = 'mysqldump --no-defaults %s';
		$command_esc_args = array( DB_NAME );

		if ( isset( $assoc_args['tables'] ) ) {
			$tables = explode( ',', trim( $assoc_args['tables'], ',' ) );
			unset( $assoc_args['tables'] );
			$command .= ' --tables';
			foreach ( $tables as $table ) {
				$command .= ' %s';
				$command_esc_args[] = trim( $table );
			}
		}

		$escaped_command = call_user_func_array( '\WP_CLI\Utils\esc_cmd', array_merge( array( $command ), $command_esc_args ) );

		self::run( $escaped_command, $assoc_args );

		if ( ! $stdout ) {
			WP_CLI::success( sprintf( 'Exported to %s', $result_file ) );
		}
	}

	/**
	 * Import database from a file or from STDIN.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : The name of the SQL file to import. If '-', then reads from STDIN. If omitted, it will look for '{dbname}.sql'.
	 */
	function import( $args, $assoc_args ) {
		$result_file = $this->get_file_name( $args );

		if ( '-' === $result_file ) {
			$descriptors = array(
				STDIN,
				STDOUT,
				STDERR,
			);
		} else {
			if ( ! file_exists( $result_file ) ) {
				WP_CLI::error( sprintf( 'Import file missing: %s', $result_file ) );
			}

			$descriptors = array(
				array( 'file', $result_file, 'r' ),
				STDOUT,
				STDERR,
			);
		}

		self::run( 'mysql --no-defaults --no-auto-rehash', array(
			'database' => DB_NAME
		), $descriptors );

		WP_CLI::success( sprintf( 'Imported from %s', $result_file ) );
	}

	/**
	 * List the database tables.
	 *
	 * ## OPTIONS
	 *
	 * [--scope=<scope>]
	 * : Can be all, global, ms_global, blog, or old tables. Defaults to all.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export only tables for a single site
	 *     wp db export --tables=$(wp db tables --url=sub.example.com | tr '\n' ',')
	 */
	function tables( $args, $assoc_args ) {
		global $wpdb;

		$scope = \WP_CLI\Utils\get_flag_value( $assoc_args, 'scope', 'all' );

		$tables = $wpdb->tables( $scope );

		foreach ( $tables as $table ) {
			WP_CLI::line( $table );
		}
	}

	private function get_file_name( $args ) {
		if ( empty( $args ) )
			return sprintf( '%s.sql', DB_NAME );

		return $args[0];
	}

	private static function get_create_query() {

		$create_query = sprintf( 'CREATE DATABASE `%s`', DB_NAME );
		if ( defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$create_query .= sprintf( ' DEFAULT CHARSET `%s`', constant( 'DB_CHARSET' ) );
		}
		if ( defined( 'DB_COLLATE' ) && constant( 'DB_COLLATE' ) ) {
			$create_query .= sprintf( ' DEFAULT COLLATE `%s`', constant( 'DB_COLLATE' ) );
		}
		return $create_query;
	}

	private static function run_query( $query ) {
		self::run( 'mysql --no-defaults --no-auto-rehash', array( 'execute' => $query ) );
	}

	private static function run( $cmd, $assoc_args = array(), $descriptors = null ) {
		$required = array(
			'host' => DB_HOST,
			'user' => DB_USER,
			'pass' => DB_PASSWORD,
		);

		if ( defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$required['default-character-set'] = constant( 'DB_CHARSET' );
		}

		$final_args = array_merge( $assoc_args, $required );

		Utils\run_mysql_command( $cmd, $final_args, $descriptors );
	}
}

WP_CLI::add_command( 'db', 'DB_Command' );

