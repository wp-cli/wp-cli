<?php

namespace WP_CLI\Iterators;

use Iterator;

/**
 * Iterates over results of a query, split into many queries via LIMIT and OFFSET
 *
 * @source https://gist.github.com/4060005
 *
 * @implements \Iterator<int, mixed>
 */
class Query implements Iterator {

	/**
	 * How many rows to retrieve at once.
	 *
	 * @var int
	 */
	private $chunk_size;

	/**
	 * The query as a string.
	 *
	 * @var string
	 */
	private $query = '';

	/**
	 * The count query as a string.
	 *
	 * @var string
	 */
	private $count_query = '';

	/**
	 * The global index in the iterator.
	 *
	 * @var int
	 */
	private $global_index = 0;

	/**
	 * The index in the current chunk of results.
	 *
	 * @var int
	 */
	private $index_in_results = 0;

	/**
	 * The current chunk of results.
	 *
	 * @var array
	 */
	private $results = [];

	/**
	 * The total row count.
	 *
	 * @var int
	 */
	private $row_count = 0;

	/**
	 * The current offset for queries.
	 *
	 * @var int
	 */
	private $offset = 0;

	/**
	 * The database connection object.
	 *
	 * @var \wpdb
	 */
	private $db = null;

	/**
	 * Whether the iterator is depleted.
	 *
	 * @var bool
	 */
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
	 * @param int $chunk_size How many rows to retrieve at once; default value is 500 (optional)
	 */
	public function __construct( $query, $chunk_size = 500 ) {
		/**
		 * @var \wpdb $wpdb
		 */
		global $wpdb;
		$this->query = $query;

		$this->count_query = (string) preg_replace( '/^.*? FROM /', 'SELECT COUNT(*) FROM ', $query, 1, $replacements );
		if ( 1 !== $replacements ) {
			$this->count_query = '';
		}

		$this->chunk_size = $chunk_size;

		$this->db = $wpdb;
	}

	/**
	 * Reduces the offset when the query row count shrinks
	 *
	 * In cases where the iterated rows are being updated such that they will no
	 * longer be returned by the original query, the offset must be reduced to
	 * iterate over all remaining rows.
	 */
	private function adjust_offset_for_shrinking_result_set(): void {
		if ( empty( $this->count_query ) ) {
			return;
		}

		$row_count = (int) $this->db->get_var( $this->count_query );

		if ( $row_count < $this->row_count ) {
			$this->offset -= $this->row_count - $row_count;
		}

		$this->row_count = $row_count;
	}

	private function load_items_from_db() {
		$this->adjust_offset_for_shrinking_result_set();

		$query   = $this->query . sprintf( ' LIMIT %d OFFSET %d', $this->chunk_size, $this->offset );
		$results = $this->db->get_results( $query );

		if ( ! $results ) {
			if ( $this->db->last_error ) {
				throw new Exception( 'Database error: ' . $this->db->last_error );
			}

			return false;
		}

		$this->results = $results;

		$this->offset += $this->chunk_size;
		return true;
	}

	#[\ReturnTypeWillChange]
	public function current() {
		return $this->results[ $this->index_in_results ];
	}

	#[\ReturnTypeWillChange]
	public function key() {
		return $this->global_index;
	}

	#[\ReturnTypeWillChange]
	public function next() {
		++$this->index_in_results;
		++$this->global_index;
	}

	#[\ReturnTypeWillChange]
	public function rewind() {
		$this->results          = [];
		$this->global_index     = 0;
		$this->index_in_results = 0;
		$this->offset           = 0;
		$this->depleted         = false;
	}

	#[\ReturnTypeWillChange]
	public function valid() {
		if ( $this->depleted ) {
			return false;
		}

		if ( ! isset( $this->results[ $this->index_in_results ] ) ) {
			$items_loaded = $this->load_items_from_db();

			if ( ! $items_loaded ) {
				$this->rewind();
				$this->depleted = true;
				return false;
			}

			$this->index_in_results = 0;
		}

		return true;
	}
}
