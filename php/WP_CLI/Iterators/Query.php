<?php

namespace WP_CLI\Iterators;

/**
 * Iterates over results of a query, split into many queries via LIMIT and OFFSET
 *
 * @source https://gist.github.com/4060005
 */
class Query implements \Iterator {

	private $chunk_size;
	private $query = '';
	private $count_query = '';

	private $global_index = 0;
	private $index_in_results = 0;
	private $results = array();
	private $row_count = 0;
	private $offset = 0;
	private $db = null;
	private $depleted = false;

	/**
	 * Creates a new query iterator
	 *
	 * This will loop over all users, but will retrieve them 100 by 100:
	 * <code>
	 * foreach( new Iterators\Query( 'SELECT * FROM users', 100 ) as $user ) {
	 *     tickle( $user );
	 * }
	 * </code>
	 *
	 * @param string $query The query as a string. It shouldn't include any LIMIT clauses
	 * @param number $chunk_size How many rows to retrieve at once; default value is 500 (optional)
	 */
	public function __construct( $query, $chunk_size = 500 ) {
		$this->query = $query;

		$this->count_query = preg_replace( '/^.*? FROM /', 'SELECT COUNT(*) FROM ', $query, 1, $replacements );
		if ( $replacements != 1 )
			$this->count_query = '';

		$this->chunk_size = $chunk_size;

		$this->db = $GLOBALS['wpdb'];
	}

	/**
	 * Reduces the offset when the query row count shrinks
	 * 
	 * In cases where the iterated rows are being updated such that they will no 
	 * longer be returned by the original query, the offset must be reduced to
	 * iterate over all remaining rows.
	 */
	private function adjust_offset_for_shrinking_result_set() {
		if ( empty( $this->count_query ) )
			return;

		$row_count = $this->db->get_var( $this->count_query );

		if ( $row_count < $this->row_count )
			$this->offset -= $this->row_count - $row_count;

		$this->row_count = $row_count;
	}

	private function load_items_from_db() {
		$this->adjust_offset_for_shrinking_result_set();

		$query = $this->query . sprintf( ' LIMIT %d OFFSET %d', $this->chunk_size, $this->offset );
		$this->results = $this->db->get_results( $query );

		if ( !$this->results ) {
			if ( $this->db->last_error ) {
				throw new Exception( 'Database error: ' . $this->db->last_error );
			} else {
				return false;
			}
		}

		$this->offset += $this->chunk_size;
		return true;
	}

	function current() {
		return $this->results[ $this->index_in_results ];
	}

	function key() {
		return $this->global_index;
	}

	function next() {
		$this->index_in_results++;
		$this->global_index++;
	}

	function rewind() {
		$this->results = array();
		$this->global_index = 0;
		$this->index_in_results = 0;
		$this->offset = 0;
		$this->depleted = false;
	}

	function valid() {
		if ( $this->depleted ) {
			return false;
		}

		if ( !isset( $this->results[ $this->index_in_results ] ) ) {
			$items_loaded = $this->load_items_from_db();

			if ( !$items_loaded ) {
				$this->rewind();
				$this->depleted = true;
				return false;
			}

			$this->index_in_results = 0;
		}

		return true;
	}
}

