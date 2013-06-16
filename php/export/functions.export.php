<?php

function wp_export( $args = array() ) {
	$defaults = array(
		'filters' => array(),
		'format' => 'WP_Export_WXR_Formatter',
		'writer' => 'WP_Export_Returner',
		'writer_args' => null,
	);
	$args = wp_parse_args( $args, $defaults );
	$export_query = new WP_Export_Query( $args['filters'] );
	$formatter = new $args['format']( $export_query );
	$writer = new $args['writer']( $formatter, $args['writer_args'] );
	try {
		return $writer->export();
	} catch ( WP_Export_Exception $e ) {
		return new WP_Error( 'wp-export-error', $e->getMessage() );
	}
}

function wp_export_new_style_args_from_old_style_args( $args ) {
	if ( isset( $args['content'] ) ) {
		if ( 'all' == $args['content'] ) {
			unset( $args['content'] );
		} else {
			$args['post_type'] = $args['content'];
		}
	}
	return $args;
}
