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
	 * This command will go through all rows in all tables and will replace all appearances of the old string with the new one.
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
	 * [--recurse-objects]
	 * : Enable recursing into objects to replace strings
	 *
	 * ## EXAMPLES
	 *
	 *     wp search-replace 'http://example.dev' 'http://example.com' --skip-columns=guid
	 *
	 *     wp search-replace 'foo' 'bar' wp_posts wp_postmeta wp_terms --dry-run
	 */
	public function __invoke( $args, $assoc_args ) {
		$old = array_shift( $args );
		$new = array_shift( $args );
		$total = 0;
		$report = array();
		$dry_run = isset( $assoc_args['dry-run'] );
		$recurse_objects = isset( $assoc_args['recurse-objects'] );

		if ( isset( $assoc_args['skip-columns'] ) )
			$skip_columns = explode( ',', $assoc_args['skip-columns'] );
		else
			$skip_columns = array();

		// never mess with hashed passwords
		$skip_columns[] = 'user_pass';

		$tables = self::get_table_list( $args, isset( $assoc_args['network'] ) );

		foreach ( $tables as $table ) {
			list( $primary_key, $columns ) = self::get_columns( $table );

			// since we'll be updating one row at a time,
			// we need a primary key to identify the row
			if ( null === $primary_key ) {
				$report[] = array( $table, '', 'skipped' );
				continue;
			}

			foreach ( $columns as $col ) {
				if ( in_array( $col, $skip_columns ) )
					continue;

				$count = self::handle_col( $col, $primary_key, $table, $old, $new, $dry_run, $recurse_objects );

				$report[] = array( $table, $col, $count );

				$total += $count;
			}
		}

		$table = new \cli\Table();
		$table->setHeaders( array( 'Table', 'Column', 'Replacements' ) );
		$table->setRows( $report );
		$table->display();

		if ( !$dry_run )
			WP_CLI::success( "Made $total replacements." );
	}

	private static function get_table_list( $args, $network ) {
		global $wpdb;

		if ( !empty( $args ) )
			return $args;

		$prefix = $network ? $wpdb->base_prefix : $wpdb->prefix;

		return $wpdb->get_col( $wpdb->prepare( "SHOW TABLES LIKE %s", like_escape( $prefix ) . '%' ) );
	}

	private static function handle_col( $col, $primary_key, $table, $old, $new, $dry_run, $recurse_objects ) {
		global $wpdb;

		// We don't want to have to generate thousands of rows when running the test suite
		$chunk_size = getenv( 'BEHAT_RUN' ) ? 10 : 1000;

		$fields = array( $primary_key, $col );
		$args = array(
			'table' => $table,
			'fields' => $fields,
			'where' => "`$col`" . ' LIKE "%' . like_escape( esc_sql( $old ) ) . '%"',
			'chunk_size' => $chunk_size
		);

		$it = new \WP_CLI\Iterators\Table( $args );

		$count = 0;

		foreach ( $it as $row ) {
			if ( '' === $row->$col )
				continue;

			$value = self::recursive_unserialize_replace( $old, $new, $row->$col, false, $recurse_objects );

			if ( $dry_run ) {
				if ( $value != $row->$col )
					$count++;
			} else {
				$count += $wpdb->update( $table,
					array( $col => $value ),
					array( $primary_key => $row->$primary_key )
				);
			}
		}

		return $count;
	}

	/**
	 * Take a serialised array and unserialise it replacing elements as needed and
	 * unserialising any subordinate arrays and performing the replace on those too.
	 * Ignores any serialized objects.
	 *
	 * Initial code from https://github.com/interconnectit/Search-Replace-DB
	 *
	 * @param string       $from            String we're looking to replace.
	 * @param string       $to              What we want it to be replaced with
	 * @param array|string $data            Used to pass any subordinate arrays back to in.
	 * @param bool         $serialised      Does the array passed via $data need serialising.
	 * @param bool         $recurse_objects Should objects be recursively replaced.
	 * @param int          $max_recursion   How many levels to recurse into the data, if $recurse_objects is set to true.
	 * @param int          $recursion_level Current recursion depth within the original data.
	 * @param array        $visited_data    Data that has been seen in previous recursion iterations.
	 *
	 * @return array    The original array with all elements replaced as needed.
	 */
	private static function recursive_unserialize_replace( $from = '', $to = '', $data = '', $serialised = false, $recurse_objects = false, $max_recursion = -1, $recursion_level = 0, &$visited_data = array() ) {

		// some unseriliased data cannot be re-serialised eg. SimpleXMLElements
		try {

			if ( $recurse_objects ) {

				// If no maximum recursion level is set, use the XDebug limit if it exists
				if ( -1 == $max_recursion ) {
					// Get the XDebug nesting level. Will be zero (no limit) if no value is set
					$max_recursion = intval( ini_get( 'xdebug.max_nesting_level' ) );
				}

				// If we've reached the maximum recursion level, short circuit
				if ( $max_recursion != 0 && $recursion_level >= $max_recursion ) {
					WP_CLI::warning("Maximum recursion level of $max_recursion reached");
					return $data;
				}

				if ( ( is_array( $data ) || is_object( $data ) ) ) {
					// If we've seen this exact object or array before, short circuit
					if ( in_array( $data, $visited_data, true ) ) {
						return $data; // Avoid infinite loops when there's a cycle
					}
					// Add this data to the list of
					$visited_data[] = $data;
				}
			}

			if ( is_string( $data ) && ( $unserialized = @unserialize( $data ) ) !== false ) {
				$data = self::recursive_unserialize_replace( $from, $to, $unserialized, true, $recurse_objects, $max_recursion, $recursion_level + 1 );
			}

			elseif ( is_array( $data ) ) {
				foreach ( $data as $key => $value ) {
					$data[ $key ] = &self::recursive_unserialize_replace( $from, $to, $value, false, $recurse_objects, $max_recursion, $recursion_level + 1, $visited_data );
				}
			}

			elseif ( $recurse_objects && is_object( $data ) ) {
				foreach ( $data as $key => $value ) {
					$data->$key = self::recursive_unserialize_replace( $from, $to, $value, false, $recurse_objects, $max_recursion, $recursion_level + 1, $visited_data );
				}
			}

			else if ( is_string( $data ) ) {
				$data = str_replace( $from, $to, $data );
			}

			if ( $serialised )
				return serialize( $data );

		} catch( Exception $error ) {

		}

		return $data;
	}

	private static function get_columns( $table ) {
		global $wpdb;

		$primary_key = null;

		$columns = array();

		foreach ( $wpdb->get_results( "DESCRIBE $table" ) as $col ) {
			if ( 'PRI' === $col->Key ) {
				$primary_key = $col->Field;
				continue;
			}

			if ( !self::is_text_col( $col->Type ) )
				continue;

			$columns[] = $col->Field;
		}

		return array( $primary_key, $columns );
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

