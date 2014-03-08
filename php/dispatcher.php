<?php

namespace WP_CLI\Dispatcher;

function get_path( $command ) {
	$path = array();

	do {
		array_unshift( $path, $command->get_name() );
	} while ( $command = $command->get_parent() );

	return $path;
}

