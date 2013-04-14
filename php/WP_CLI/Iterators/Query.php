<?php

namespace WP_CLI\Iterators;

/**
 * Iterates over results of a query, split into many queries via LIMIT and OFFSET
 *
 * @source https://gist.github.com/4060005
 */
class Query implements \Iterator {

	private $limit = 500;
	private $query = '';

	private $global_index = 0;
	private $index_in_results = 0;
	private $results = array();
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
	 * @param number $limit How many rows to retrieve at once; default value is 500 (optional)
	 */
	public function __construct( $query, $limit = 500 ) {
		$this->query = $query;
		$this->limit = $limit;

		$this->db = $GLOBALS['wpdb'];
	}

	private function load_items_from_db() {
		$query = $this->query . sprintf( ' LIMIT %d OFFSET %d', $this->limit, $this->offset );
		$this->results = $this->db->get_results( $query );

		if ( !$this->results ) {
			if ( $this->db->last_error ) {
				throw new Iterators\Exception( 'Database error: ' . $this->db->last_error );
			} else {
				return false;
			}
		}

		$this->offset += $this->limit;
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

