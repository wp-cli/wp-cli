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
	 * @synopsis <old> <new> [<table>...] [--dry-run]
	 */
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;

		$old = array_shift( $args );
		$new = array_shift( $args );

		if ( !empty( $args ) ) {
			$tables = $args;
		} else {
			$tables = $wpdb->tables( 'blog' );
		}

		$total = 0;

		$report = array();

		$dry_run = isset( $assoc_args['dry-run'] );

		foreach ( $tables as $table ) {
			list( $primary_key, $columns ) = self::get_columns( $table );

			foreach ( $columns as $col ) {
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

	private static function handle_col( $col, $primary_key, $table, $old, $new, $dry_run ) {
		global $wpdb;

		$args = array(
			'table' => $table,
			'fields' => array( $primary_key, $col ),
			'where' => $col . ' LIKE "%' . like_escape( esc_sql( $old ) ) . '%"',
			'limit' => 1000
		);

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

