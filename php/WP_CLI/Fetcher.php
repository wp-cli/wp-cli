<?php

namespace WP_CLI;

interface Fetcher {

	// Returns the item if found; otherwise returns false
	function get( $id );

	// Returns the item if found; otherwise calls WP_CLI::error()
	function get_check( $id );

	// Returns the list of found items
	function get_many( $ids );
}

