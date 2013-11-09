<?php

namespace WP_CLI;

interface Fetcher {

	// Returns the object if found; otherwise returns false
	function get( $id );

	// Returns the object if found; otherwise calls WP_CLI::error()
	function get_check( $id );

	// Returns the list of found objects
	function get_many( $ids );
}

