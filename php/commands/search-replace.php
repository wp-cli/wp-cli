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
	 * @synopsis <old> <new> [<table>...] [--skip-columns=<columns>] [--dry-run] [--multisite]
	 */
	public function __invoke( $args, $assoc_args ) {
		$old = array_shift( $args );
		$new = array_shift( $args );
		$total = 0;
		$report = array();
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( isset( $assoc_args['skip-columns'] ) )
			$skip_columns = explode( ',', $assoc_args['skip-columns'] );
		else
			$skip_columns = array();

		// never mess with hashed passwords
		$skip_columns[] = 'user_pass';

		$tables = self::get_table_list( $args, isset( $assoc_args['multisite'] ) );

		foreach ( $tables as $table ) {
			list( $primary_key, $columns ) = self::get_columns( $table );
			foreach ( $columns as $col ) {
				if ( in_array( $col, $skip_columns ) )
					continue;

				$count = self::handle_col( $col, $primary_key, $table, $old, $new,
					 $dry_run );

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

	private static function get_table_list( $args, $multisite ) {
		global $wpdb;

		if ( !empty( $args ) )
			return $args;

		$tables = $wpdb->tables( 'blog' );

		if ( $multisite ) {
			$blogs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}blogs ORDER BY blog_id" ) );
			$mu_tables = $wpdb->tables( 'global' );

			if ( ! $wpdb->query( "SHOW TABLES LIKE '{$mu_tables['sitecategories']}' " ) )
				unset( $mu_tables['sitecategories'] );	//table $prefix_sitecategories not found

			foreach ( $blogs as $blog ) {
				if ( $blog->blog_id == 1 )
					continue;

				foreach ( $tables as $table_ref => $table ) {
					$tbl = "{$wpdb->prefix}{$blog->blog_id}_{$table_ref}";
					$mu_tables[$tbl] = $tbl;
				}
			}

			$tables = array_merge( $mu_tables, $tables );
		}

		return $tables;
	}

	private static function handle_col( $col, $primary_key, $table, $old, $new, $dry_run ) {
		global $wpdb;

		// We don't want to have to generate thousands of rows when running the test suite
		$chunk_size = getenv( 'BEHAT_RUN' ) ? 10 : 1000;

		$args = array(
			'table' => $table,
			'fields' => array( $primary_key, $col ),
			'where' => $col . ' LIKE "%' . like_escape( esc_sql( $old ) ) . '%"',
			'chunk_size' => $chunk_size
		);

		if ( $primary_key===null )
			return "skipped";

		$it = new \WP_CLI\Iterators\Table( $args );

		$count = 0;

		foreach ( $it as $row ) {
			if ( '' === $row->$col )
				continue;

			$value = \WP_CLI\Utils\recursive_unserialize_replace( $old, $new, $row->$col );

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

