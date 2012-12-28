<?php

// Utilities that depend on WordPress code.

namespace WP_CLI\Utils;

// Handle --user parameter
function set_user( $assoc_args ) {
	if ( !isset( $assoc_args['user'] ) )
		return;

	$user = $assoc_args['user'];

	if ( is_numeric( $user ) ) {
		$user_id = (int) $user;
	} else {
		$user_id = (int) username_exists( $user );
	}

	if ( !$user_id || !wp_set_current_user( $user_id ) ) {
		\WP_CLI::error( sprintf( 'Could not get a user_id for this user: %s', var_export( $user, true ) ) );
	}
}

function set_wp_query() {
	if ( isset( $GLOBALS['wp_query'] ) && isset( $GLOBALS['wp'] ) ) {
		$GLOBALS['wp']->parse_request();
		$GLOBALS['wp_query']->query($GLOBALS['wp']->query_vars);
	}
}

function get_upgrader( $class ) {
	if ( !class_exists( '\WP_Upgrader' ) )
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

	return new $class( new \WP_CLI\UpgraderSkin );
}

