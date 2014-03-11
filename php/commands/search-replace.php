<?php

/**
 * Search and replace strings in the database.
 *
 * @package wp-cli
 */
class Search_Replace_Command extends WP_CLI_Command {

	/**
	 * Search/replace strings in the database.
	 *
	 * ## DESCRIPTION
	 *
	 * This command will go through all rows in all tables and will replace all
	 * appearances of the old string with the new one.
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
	 * : List of database tables to restrict the replacement to.
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
	 * [--export[=<file>]]
	 * : Write out new SQL to file instead of performing in-place replacements. If <file> is '-' or is not supplied, will output to STDOUT.
	 *
	 * [--recurse-objects]
	 * : Enable recursing into objects to replace strings
	 *
	 * ## EXAMPLES
	 *
	 *     wp search-replace 'http://example.dev' 'http://example.com' --skip-columns=guid
	 *
	 *     wp search-replace 'foo' 'bar' wp_posts wp_postmeta wp_terms --dry-run
	 *
	 *     wp search-replace 'www.example.com' 'staging.example.com' --export=staging.sql
	 *
	 *     wp search-replace 'staging.example.com' 'www.example.com' --export | mysql
	 */
	public function __invoke( $args, $assoc_args ) {
		$old = array_shift( $args );
		$new = array_shift( $args );
		$total = 0;
		$report = array();
		if ( isset( $assoc_args['dry-run'] ) && isset( $assoc_args['export'] ) ) {
			WP_CLI::error( 'You cannot supply --dry-run and --export at the same time.' );
		}
		$dry_run = isset( $assoc_args['dry-run'] );
		$recurse_objects = isset( $assoc_args['recurse-objects'] );

		$export_handle = null;
		if ( isset( $assoc_args['export'] ) ) {
			if ( true === $assoc_args['export'] ) {
				$assoc_args['export'] = '-';
			}
			if ( '-' === $assoc_args['export'] ) {
				$export_handle = STDOUT;
			}
			else {
				$export_handle = fopen( $assoc_args['export'], 'w' );
			}
			if ( false === $export_handle ) {
				WP_CLI::error( sprintf( 'Unable to open "%s" for writing', $assoc_args['export'] ) );
			}
		}

		if ( isset( $assoc_args['skip-columns'] ) )
			$skip_columns = explode( ',', $assoc_args['skip-columns'] );
		else
			$skip_columns = array();

		// never mess with hashed passwords
		$skip_columns[] = 'user_pass';

		$tables = self::get_table_list( $args, isset( $assoc_args['network'] ) );

		foreach ( $tables as $table ) {
			$table_report = self::handle_table( $table, $skip_columns, $old, $new, $dry_run, $export_handle, $recurse_objects );
			foreach ( $table_report as $col_report ) {
				if ( 'skipped' !== $col_report[2] ) {
					$total += $col_report[2];
				}
				$report[] = $col_report;
			}
		}

		if ( STDOUT !== $export_handle ) {
			$table = new \cli\Table();
			$table->setHeaders( array( 'Table', 'Column', 'Replacements' ) );
			$table->setRows( $report );
			$table->display();
		}

		if ( ! $dry_run && STDOUT !== $export_handle ) {
			WP_CLI::success( "Made $total replacements." );
		}
	}

	private static function get_table_list( $args, $network ) {
		global $wpdb;

		if ( !empty( $args ) )
			return $args;

		$prefix = $network ? $wpdb->base_prefix : $wpdb->prefix;

		return $wpdb->get_col( $wpdb->prepare( "SHOW TABLES LIKE %s", like_escape( $prefix ) . '%' ) );
	}

	private static function handle_table( $table, $skip_columns, $old, $new, $dry_run, $export_handle, $recurse_objects ) {
		global $wpdb;

		$table_report = array();

		list( $primary_key, $text_columns, $all_columns ) = self::get_columns( $table );

		// Since we'll be updating one row at a time,
		// we need a primary key to identify the row;
		// likewise, skip tables if has no text fields.
		// Exporting does not have this limitation
		$is_skipped = ( is_null( $primary_key ) || empty( $text_columns ) ) && is_null( $export_handle );
		if ( $is_skipped ) {
			$table_report[] = array( $table, '', 'skipped' );
			return $table_report;
		}

		// We don't want to have to generate thousands of rows when running the test suite
		$chunk_size = getenv( 'BEHAT_RUN' ) ? 10 : 1000;

		$args = array(
			'table' => $table,
			'chunk_size' => $chunk_size,
		);
		if ( $export_handle ) {
			fwrite( $export_handle, "\nDROP TABLE IF EXISTS `$table`;\n" );
			$row = $wpdb->get_row( "SHOW CREATE TABLE `$table`", ARRAY_N );
			fwrite( $export_handle, $row[1] . ";\n" );
		} else {
			$fields = array_merge( array( $primary_key ), $text_columns );
			$where = array();
			$like = like_escape( esc_sql( $old ) );
			foreach ( $text_columns as $col ) {
				$tpl_vars = compact( 'col', 'like' );
				$where[] = str_replace(
					array_keys( $tpl_vars ),
					array_values( $tpl_vars ),
					'`col` LIKE "%like%"'
				);
			}
			$args = array_merge( $args, array(
				'fields' => $fields,
				'where' => join( ' OR ', $where ),
			) );
		}

		$it = new \WP_CLI\Iterators\Table( $args );


		if ( $export_handle ) {
			$target_cols = $all_columns;
		}
		else {
			$target_cols = array_diff( $text_columns, $skip_columns );
		}
		$col_counts = array_fill_keys( $target_cols, 0 );

		$replacer = new \WP_CLI\SearchReplacer( $old, $new, $recurse_objects );

		foreach ( $it as $row ) {
			$row_fields = array();
			foreach ( $target_cols as $col ) {
				$is_skipped = in_array( $col, $skip_columns );
				if ( $is_skipped && ! $export_handle ) {
					continue;
				}

				$old_value = $row->$col;
				$new_value = $old_value;
				if ( '' !== $old_value && ! $is_skipped ) {
					$new_value = $replacer->run( $old_value );
				}

				if ( $export_handle || $dry_run ) {
					$row_fields[$col] = $new_value;
					if ( $new_value !== $old_value ) {
						$col_counts[$col] += 1;
					}
				} else {
					$col_counts[$col] += $wpdb->update( $table,
						array( $col => $new_value ),
						array( $primary_key => $row->$primary_key )
					);
				}
			}

			if ( $export_handle ) {
				$sql = "INSERT INTO `$table` (";
				$sql .= join( ', ', array_map(
					function ( $field ) {
						return "`$field`";
					},
					array_keys( $row_fields )
				) );
				$sql .= ') VALUES (';
				$sql .= join( ', ', array_fill( 0, count( $row_fields ), '%s' ) );
				$sql .= ");\n";
				$sql = $wpdb->prepare( $sql, array_values( $row_fields ) );
				fwrite( $export_handle, $sql );
			}
		}

		foreach ( $col_counts as $col => $col_count ) {
			$table_report[] = array( $table, $col, $col_count );
		}

		return $table_report;
	}

	private static function get_columns( $table ) {
		global $wpdb;

		$primary_key = null;
		$text_columns = array();
		$all_columns = array();

		foreach ( $wpdb->get_results( "DESCRIBE $table" ) as $col ) {
			if ( 'PRI' === $col->Key ) {
				$primary_key = $col->Field;
			}

			if ( self::is_text_col( $col->Type ) ) {
				$text_columns[] = $col->Field;
			}

			$all_columns[] = $col->Field;
		}

		return array( $primary_key, $text_columns, $all_columns );
	}

	private static function is_text_col( $type ) {
		foreach ( array( 'text', 'varchar' ) as $token ) {
			if ( false !== strpos( $type, $token ) )
				return true;
		}

		return false;
	}
}

WP_CLI::add_command( 'search-replace', 'Search_Replace_Command' );

