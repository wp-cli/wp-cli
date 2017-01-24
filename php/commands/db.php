<?php

use \WP_CLI\Utils;

/**
 * Perform basic database operations using credentials stored in wp-config.php
 *
 * ## EXAMPLES
 *
 *     # Create a new database.
 *     $ wp db create
 *     Success: Database created.
 *
 *     # Drop an existing database.
 *     $ wp db drop --yes
 *     Success: Database dropped.
 *
 *     # Reset the current database.
 *     $ wp db reset --yes
 *     Success: Database reset.
 *
 *     # Execute a SQL query stored in a file.
 *     $ wp db query < debug.sql
 */
class DB_Command extends WP_CLI_Command {

	/**
	 * Create a new database.
	 *
	 * Runs `CREATE_DATABASE` SQL statement using `DB_HOST`, `DB_NAME`,
	 * `DB_USER` and `DB_PASSWORD` database credentials specified in
	 * wp-config.php.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db create
	 *     Success: Database created.
	 */
	public function create( $_, $assoc_args ) {

		self::run_query( self::get_create_query() );

		WP_CLI::success( "Database created." );
	}

	/**
	 * Delete the existing database.
	 *
	 * Runs `DROP_DATABASE` SQL statement using `DB_HOST`, `DB_NAME`,
	 * `DB_USER` and `DB_PASSWORD` database credentials specified in
	 * wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db drop --yes
	 *     Success: Database dropped.
	 */
	public function drop( $_, $assoc_args ) {
		WP_CLI::confirm( "Are you sure you want to drop the database?", $assoc_args );

		self::run_query( sprintf( 'DROP DATABASE `%s`', DB_NAME ) );

		WP_CLI::success( "Database dropped." );
	}

	/**
	 * Remove all tables from the database.
	 *
	 * Runs `DROP_DATABASE` and `CREATE_DATABASE` SQL statements using
	 * `DB_HOST`, `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [--yes]
	 * : Answer yes to the confirmation message.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db reset --yes
	 *     Success: Database reset.
	 */
	public function reset( $_, $assoc_args ) {
		WP_CLI::confirm( "Are you sure you want to reset the database?", $assoc_args );

		self::run_query( sprintf( 'DROP DATABASE IF EXISTS `%s`', DB_NAME ) );
		self::run_query( self::get_create_query() );

		WP_CLI::success( "Database reset." );
	}

	/**
	 * Check the current status of the database.
	 *
	 * Runs `mysqlcheck` utility with `--check` using `DB_HOST`,
	 * `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * [See docs](http://dev.mysql.com/doc/refman/5.7/en/check-table.html)
	 * for more details on the `CHECK TABLE` statement.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db check
	 *     Success: Database checked.
	 */
	public function check() {
		self::run( Utils\esc_cmd( 'mysqlcheck --no-defaults %s', DB_NAME ), array(
			'check' => true,
		) );

		WP_CLI::success( "Database checked." );
	}

	/**
	 * Optimize the database.
	 *
	 * Runs `mysqlcheck` utility with `--optimize=true` using `DB_HOST`,
	 * `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * [See docs](http://dev.mysql.com/doc/refman/5.7/en/optimize-table.html)
	 * for more details on the `OPTIMIZE TABLE` statement.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db optimize
	 *     Success: Database optimized.
	 */
	public function optimize() {
		self::run( Utils\esc_cmd( 'mysqlcheck --no-defaults %s', DB_NAME ), array(
			'optimize' => true,
		) );

		WP_CLI::success( "Database optimized." );
	}

	/**
	 * Repair the database.
	 *
	 * Runs `mysqlcheck` utility with `--repair=true` using `DB_HOST`,
	 * `DB_NAME`, `DB_USER` and `DB_PASSWORD` database credentials
	 * specified in wp-config.php.
	 *
	 * [See docs](http://dev.mysql.com/doc/refman/5.7/en/repair-table.html) for
	 * more details on the `REPAIR TABLE` statement.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp db repair
	 *     Success: Database repaired.
	 */
	public function repair() {
		self::run( Utils\esc_cmd( 'mysqlcheck --no-defaults %s', DB_NAME ), array(
			'repair' => true,
		) );

		WP_CLI::success( "Database repaired." );
	}

	/**
	 * Open a MySQL console using credentials from wp-config.php
	 *
	 * ## OPTIONS
	 *
	 * [--database=<database>]
	 * : Use a specific database. Defaults to DB_NAME.
	 *
	 * [--default-character-set=<character-set>]
	 * : Use a specific character set. Defaults to DB_CHARSET when defined.
	 *
	 * [--<field>=<value>]
	 * : Extra arguments to pass to the MySQL executable.
	 *
	 * ## EXAMPLES
	 *
	 *     # Open MySQL console
	 *     $ wp db cli
	 *     mysql>
	 *
	 * @alias connect
	 */
	public function cli( $args, $assoc_args ) {
		if ( ! isset( $assoc_args['database'] ) ) {
			$assoc_args['database'] = DB_NAME;
		}

		self::run( 'mysql --no-defaults --no-auto-rehash', $assoc_args );
	}

	/**
	 * Execute a SQL query against the database.
	 *
	 * Executes an arbitrary SQL query using `DB_HOST`, `DB_NAME`, `DB_USER`
	 *  and `DB_PASSWORD` database credentials specified in wp-config.php.
	 *
	 * ## OPTIONS
	 *
	 * [<sql>]
	 * : A SQL query. If not passed, will try to read from STDIN.
	 *
	 * [--<field>=<value>]
	 * : Extra arguments to pass to mysql.
	 *
	 * ## EXAMPLES
	 *
	 *     # Execute a query stored in a file
	 *     $ wp db query < debug.sql
	 *
	 *     # Check all tables in the database
	 *     $ wp db query "CHECK TABLE $(wp db tables | paste -s -d',');"
	 *     +---------------------------------------+-------+----------+----------+
	 *     | Table                                 | Op    | Msg_type | Msg_text |
	 *     +---------------------------------------+-------+----------+----------+
	 *     | wordpress_dbase.wp_users              | check | status   | OK       |
	 *     | wordpress_dbase.wp_usermeta           | check | status   | OK       |
	 *     | wordpress_dbase.wp_posts              | check | status   | OK       |
	 *     | wordpress_dbase.wp_comments           | check | status   | OK       |
	 *     | wordpress_dbase.wp_links              | check | status   | OK       |
	 *     | wordpress_dbase.wp_options            | check | status   | OK       |
	 *     | wordpress_dbase.wp_postmeta           | check | status   | OK       |
	 *     | wordpress_dbase.wp_terms              | check | status   | OK       |
	 *     | wordpress_dbase.wp_term_taxonomy      | check | status   | OK       |
	 *     | wordpress_dbase.wp_term_relationships | check | status   | OK       |
	 *     | wordpress_dbase.wp_termmeta           | check | status   | OK       |
	 *     | wordpress_dbase.wp_commentmeta        | check | status   | OK       |
	 *     +---------------------------------------+-------+----------+----------+
	 *
	 *     # Pass extra arguments through to MySQL
	 *     $ wp db query 'SELECT * FROM wp_options WHERE option_name="home"' --skip-column-names
	 *     +---+------+------------------------------+-----+
	 *     | 2 | home | http://wordpress-develop.dev | yes |
	 *     +---+------+------------------------------+-----+
	 */
	public function query( $args, $assoc_args ) {
		$assoc_args['database'] = DB_NAME;

		// The query might come from STDIN
		if ( !empty( $args ) ) {
			$assoc_args['execute'] = $args[0];
		}

		self::run( 'mysql --no-defaults --no-auto-rehash', $assoc_args );
	}

	/**
	 * Exports the database to a file or to STDOUT.
	 *
	 * Runs `mysqldump` utility using `DB_HOST`, `DB_NAME`, `DB_USER` and
	 * `DB_PASSWORD` database credentials specified in wp-config.php.
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
	 * [--porcelain]
	 * : Output filename for the exported database.
	 *
	 * ## EXAMPLES
	 *
	 *     # Export database with drop query included
	 *     $ wp db export --add-drop-table
	 *     Success: Exported to 'wordpress_dbase.sql'.
	 *
	 *     # Export certain tables
	 *     $ wp db export --tables=wp_options,wp_users
	 *     Success: Exported to 'wordpress_dbase.sql'.
	 *
	 *     # Export all tables matching a wildcard
	 *     $ wp db export --tables=$(wp db tables 'wp_user*' --format=csv)
	 *     Success: Exported to 'wordpress_dbase.sql'.
	 *
	 *     # Export all tables matching prefix
	 *     $ wp db export --tables=$(wp db tables --all-tables-with-prefix --format=csv)
	 *     Success: Exported to 'wordpress_dbase.sql'.
	 *
	 * @alias dump
	 */
	public function export( $args, $assoc_args ) {
		if ( ! empty( $args[0] ) ) {
			$result_file = $args[0];
		} else {
			$hash = substr( md5( mt_rand() ), 0, 7 );
			$result_file = sprintf( '%s-%s.sql', DB_NAME, $hash );;
		}
		$stdout = ( '-' === $result_file );
		$porcelain = \WP_CLI\Utils\get_flag_value( $assoc_args, 'porcelain' );

		// Bail if both porcelain and STDOUT are set.
		if ( $stdout && $porcelain ) {
			WP_CLI::error( 'Porcelain is not allowed when output mode is STDOUT.' );
		}

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

		// Remove parameters not needed for SQL run.
		if ( isset( $assoc_args['porcelain'] ) ) {
			unset( $assoc_args['porcelain'] );
		}

		self::run( $escaped_command, $assoc_args );

		if ( $porcelain ) {
			WP_CLI::line( $result_file );
		}
		else if ( ! $stdout ) {
			WP_CLI::success( sprintf( "Exported to '%s'.", $result_file ) );
		}
	}

	/**
	 * Import a database from a file or from STDIN.
	 *
	 * Runs SQL queries using `DB_HOST`, `DB_NAME`, `DB_USER` and
	 * `DB_PASSWORD` database credentials specified in wp-config.php. This
	 * does not create database by itself and only performs whatever tasks are
	 * defined in the SQL.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : The name of the SQL file to import. If '-', then reads from STDIN. If omitted, it will look for '{dbname}.sql'.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import MySQL from a file.
	 *     $ wp db import wordpress_dbase.sql
	 *     Success: Imported from 'wordpress_dbase.sql'.
	 */
	public function import( $args, $assoc_args ) {
		if ( ! empty( $args[0] ) ) {
			$result_file = $args[0];
		} else {
			$result_file = sprintf( '%s.sql', DB_NAME );
		}

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

		WP_CLI::success( sprintf( "Imported from '%s'.", $result_file ) );
	}

	/**
	 * List the database tables.
	 *
	 * Defaults to all tables registered to the $wpdb database handler.
	 *
	 * ## OPTIONS
	 *
	 * [<table>...]
	 * : List tables based on wildcard search, e.g. 'wp_*_options' or 'wp_post?'.
	 *
	 * [--scope=<scope>]
	 * : Can be all, global, ms_global, blog, or old tables. Defaults to all.
	 *
	 * [--network]
	 * : List all the tables in a multisite install. Overrides --scope=<scope>.
	 *
	 * [--all-tables-with-prefix]
	 * : List all tables that match the table prefix even if not registered on $wpdb. Overrides --network.
	 *
	 * [--all-tables]
	 * : List all tables in the database, regardless of the prefix, and even if not registered on $wpdb. Overrides --all-tables-with-prefix.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: list
	 * options:
	 *   - list
	 *   - csv
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # List tables for a single site, without shared tables like 'wp_users'
	 *     $ wp db tables --scope=blog --url=sub.example.com
	 *     wp_3_posts
	 *     wp_3_comments
	 *     wp_3_options
	 *     wp_3_postmeta
	 *     wp_3_terms
	 *     wp_3_term_taxonomy
	 *     wp_3_term_relationships
	 *     wp_3_termmeta
	 *     wp_3_commentmeta
	 *
	 *     # Export only tables for a single site
	 *     $ wp db export --tables=$(wp db tables --url=sub.example.com --format=csv)
	 *     Success: Exported to wordpress_dbase.sql
	 */
	public function tables( $args, $assoc_args ) {

		$format = WP_CLI\Utils\get_flag_value( $assoc_args, 'format' );
		unset( $assoc_args['format'] );

		if ( empty( $args ) && empty( $assoc_args ) ) {
			$assoc_args['scope'] = 'all';
		}

		$tables = WP_CLI\Utils\wp_get_table_names( $args, $assoc_args );

		if ( 'csv' === $format ) {
			WP_CLI::line( implode( ',', $tables ) );
		} else {
			foreach ( $tables as $table ) {
				WP_CLI::line( $table );
			}
		}
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

		if ( ! isset( $assoc_args['default-character-set'] )
			&& defined( 'DB_CHARSET' ) && constant( 'DB_CHARSET' ) ) {
			$required['default-character-set'] = constant( 'DB_CHARSET' );
		}

		$final_args = array_merge( $assoc_args, $required );
		Utils\run_mysql_command( $cmd, $final_args, $descriptors );
	}

}

WP_CLI::add_command( 'db', 'DB_Command' );

