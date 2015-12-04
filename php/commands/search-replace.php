<?php

/**
 * Search and replace strings in the database.
 *
 * @package wp-cli
 */
class Search_Replace_Command extends WP_CLI_Command {

	private $export_handle = false;

	/**
	 * Search/replace strings in the database.
	 *
	 * ## DESCRIPTION
	 *
	 * This command will go through all rows in a selection of tables
	 * and will replace all appearances of the old string with the new one.  The
	 * default tables are those registered on the $wpdb object (usually
	 * just WordPress core tables).
	 *
	 * It will correctly handle serialized values, and will not change primary key values.
	 *
	 * ## OPTIONS
	 *
	 * <old>
	 * : The old string.
	 *
	 * <new>
	 * : The new string.
	 *
	 * [<table>...]
	 * : List of database tables to restrict the replacement to. Wildcards are supported, e.g. wp_\*_options or wp_post\?.
	 *
	 * [--network]
	 * : Search/replace through all the tables in a multisite install.
	 *
	 * [--skip-columns=<columns>]
	 * : Do not perform the replacement in the comma-separated columns.
	 *
	 * [--dry-run]
	 * : Show report, but don't perform the changes.
	 *
	 * [--precise]
	 * : Force the use of PHP (instead of SQL) which is more thorough, but slower. Use if you see issues with serialized data.
	 *
	 * [--recurse-objects]
	 * : Enable recursing into objects to replace strings. Defaults to true; pass --no-recurse-objects to disable.
	 *
	 * [--all-tables-with-prefix]
	 * : Enable replacement on any tables that match the table prefix even if not registered on wpdb
	 *
	 * [--all-tables]
	 * : Enable replacement on ALL tables in the database, regardless of the prefix, and even if not registered on $wpdb. Overrides --network and --all-tables-with-prefix.
	 *
	 * [--verbose]
	 * : Prints rows to the console as they're updated.
	 *
	 * [--regex]
	 * : Runs the search using a regular expression. Warning: search-replace will take about 15-20x longer when using --regex.
	 *
	 * [--export[=<file>]]
	 * : Write transformed data as SQL file instead of performing in-place replacements. If <file> is not supplied, will output to STDOUT.
	 *
	 * ## EXAMPLES
	 *
	 *     wp search-replace 'http://example.dev' 'http://example.com' --skip-columns=guid
	 *
	 *     wp search-replace 'foo' 'bar' wp_posts wp_postmeta wp_terms --dry-run
	 *
	 *     # Turn your production database into a local database
	 *     wp search-replace --url=example.com example.com example.dev wp_\*_options
	 */
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;
		$old             = array_shift( $args );
		$new             = array_shift( $args );
		$total           = 0;
		$report          = array();
		$dry_run         = \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run' );
		$php_only        = \WP_CLI\Utils\get_flag_value( $assoc_args, 'precise' );
		$recurse_objects = \WP_CLI\Utils\get_flag_value( $assoc_args, 'recurse-objects', true );
		$verbose         =  \WP_CLI\Utils\get_flag_value( $assoc_args, 'verbose' );
		$regex           =  \WP_CLI\Utils\get_flag_value( $assoc_args, 'regex' );

		$skip_columns = explode( ',', \WP_CLI\Utils\get_flag_value( $assoc_args, 'skip-columns' ) );

		if ( $old === $new && ! $regex ) {
			WP_CLI::warning( "Replacement value '{$old}' is identical to search value '{$new}'. Skipping operation." );
			exit;
		}

		if ( null !== ( $export = \WP_CLI\Utils\get_flag_value( $assoc_args, 'export' ) ) ) {
			if ( $dry_run ) {
				WP_CLI::error( 'You cannot supply --dry-run and --export at the same time.' );
			}
			if ( true === $export ) {
				$this->export_handle = STDOUT;
				$verbose = false;
			} else {
				$this->export_handle = fopen( $assoc_args['export'], 'w' );
				if ( false === $this->export_handle ) {
					WP_CLI::error( sprintf( 'Unable to open "%s" for writing', $assoc_args['export'] ) );
				}
			}
			$php_only = true;
		}

		// never mess with hashed passwords
		$skip_columns[] = 'user_pass';

		// Get table names based on leftover $args or supplied $assoc_args
		$tables = \WP_CLI\Utils\wp_get_table_names( $args, $assoc_args );
		foreach ( $tables as $table ) {

			if ( $this->export_handle ) {
				fwrite( $this->export_handle, "\nDROP TABLE IF EXISTS `$table`;\n" );
				$row = $wpdb->get_row( "SHOW CREATE TABLE `$table`", ARRAY_N );
				fwrite( $this->export_handle, $row[1] . ";\n" );
			}

			list( $primary_keys, $columns ) = self::get_columns( $table );

			// since we'll be updating one row at a time,
			// we need a primary key to identify the row
			if ( empty( $primary_keys ) ) {
				$report[] = array( $table, '', 'skipped' );
				continue;
			}

			foreach ( $columns as $col ) {
				if ( in_array( $col, $skip_columns ) ) {
					continue;
				}

				if ( $verbose ) {
					$this->start_time = microtime( true );
					WP_CLI::log( sprintf( 'Checking: %s.%s', $table, $col ) );
				}

				if ( ! $php_only ) {
					$serialRow = $wpdb->get_row( "SELECT * FROM `$table` WHERE `$col` REGEXP '^[aiO]:[1-9]' LIMIT 1" );
				}

				if ( $php_only || $regex || NULL !== $serialRow ) {
					$type = 'PHP';
					$count = $this->php_handle_col( $col, $primary_keys, $table, $old, $new, $dry_run, $recurse_objects, $verbose, $regex );
				} else {
					$type = 'SQL';
					$count = $this->sql_handle_col( $col, $table, $old, $new, $dry_run, $verbose );
				}

				$report[] = array( $table, $col, $count, $type );

				$total += $count;
			}
		}

		if ( $this->export_handle && STDOUT !== $this->export_handle ) {
			fclose( $this->export_handle );
		}

		// Only informational output after this point
		if ( WP_CLI::get_config( 'quiet' ) || STDOUT === $this->export_handle ) {
			return;
		}

		$table = new \cli\Table();
		$table->setHeaders( array( 'Table', 'Column', 'Replacements', 'Type' ) );
		$table->setRows( $report );
		$table->display();

		if ( ! $dry_run ) {
			$success_message = "Made $total replacements.";
			if ( $total && 'Default' !== WP_CLI\Utils\wp_get_cache_type() ) {
				$success_message .= ' Please remember to flush your persistent object cache with `wp cache flush`.';
			}
			WP_CLI::success( $success_message );
		}
	}

	private function sql_handle_col( $col, $table, $old, $new, $dry_run, $verbose ) {
		global $wpdb;

		if ( $dry_run ) {
			$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(`$col`) FROM `$table` WHERE `$col` LIKE %s;", '%' . self::esc_like( $old ) . '%' ) );
		} else {
			$count = $wpdb->query( $wpdb->prepare( "UPDATE `$table` SET `$col` = REPLACE(`$col`, %s, %s);", $old, $new ) );
		}

		if ( $verbose ) {
			$time = round( microtime( true ) - $this->start_time, 3 );
			WP_CLI::log( sprintf( '%d rows affected using SQL (in %ss)', $count, $time ) );
		}
		return $count;
	}

	private function php_handle_col( $col, $primary_keys, $table, $old, $new, $dry_run, $recurse_objects, $verbose, $regex ) {
		global $wpdb;

		// We don't want to have to generate thousands of rows when running the test suite
		$chunk_size = getenv( 'BEHAT_RUN' ) ? 10 : 1000;

		$fields = $primary_keys;
		$fields[] = $col;

		$args = array(
			'table' => $table,
			'fields' => $fields,
			'where' => $regex ? '' : "`$col`" . $wpdb->prepare( ' LIKE %s', '%' . self::esc_like( $old ) . '%' ),
			'chunk_size' => $chunk_size
		);

		$it = new \WP_CLI\Iterators\Table( $args );

		$count = 0;

		$replacer = new \WP_CLI\SearchReplacer( $old, $new, $recurse_objects, $regex );

		foreach ( $it as $row ) {
			if ( '' === $row->$col )
				continue;

			$value = $replacer->run( $row->$col );

			if ( $value === $row->$col ) {
				continue;
			}

			if ( $dry_run ) {
				if ( $value != $row->$col )
					$count++;
			} else {
				$where = array();
				foreach ( $primary_keys as $primary_key ) {
					$where[ $primary_key ] = $row->$primary_key;
				}

				$count += $wpdb->update( $table, array( $col => $value ), $where );
			}
		}

		if ( $verbose ) {
			$time = round( microtime( true ) - $this->start_time, 3 );
			WP_CLI::log( sprintf( '%d rows affected using PHP (in %ss)', $count, $time ) );
		}

		return $count;
	}

	private static function get_columns( $table ) {
		global $wpdb;

		$primary_keys = array();

		$columns = array();

		foreach ( $wpdb->get_results( "DESCRIBE $table" ) as $col ) {
			if ( 'PRI' === $col->Key ) {
				$primary_keys[] = $col->Field;
				continue;
			}

			if ( !self::is_text_col( $col->Type ) )
				continue;

			$columns[] = $col->Field;
		}

		return array( $primary_keys, $columns );
	}

	private static function is_text_col( $type ) {
		foreach ( array( 'text', 'varchar' ) as $token ) {
			if ( false !== strpos( $type, $token ) )
				return true;
		}

		return false;
	}

	private static function esc_like( $old ) {
		global $wpdb;

		// Remove notices in 4.0 and support backwards compatibility
		if( method_exists( $wpdb, 'esc_like' ) ) {
			// 4.0
			$old = $wpdb->esc_like( $old );
		} else {
			// 3.9 or less
			$old = like_escape( esc_sql( $old ) );
		}

		return $old;
	}

}

WP_CLI::add_command( 'search-replace', 'Search_Replace_Command' );

