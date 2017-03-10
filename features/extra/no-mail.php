<?php

function wp_mail( $to ) {
	// Log for testing purposes
	WP_CLI::log( "WP-CLI test suite: Sent email to {$to}." );
}

