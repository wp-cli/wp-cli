<?php

// Add the command to the wp-cli
WP_CLI::addCommand('generate', 'GenerateCommand');

/**
 * Implement generate command
 *
 * @package wp-cli
 * @subpackage commands/internals
 * @author Cristi Burca
 */
class GenerateCommand extends WP_CLI_Command {

	/**
	 * Generate posts
	 *
	 * @param string $args
	 * @return void
	 **/
	public function posts( $args, $assoc_args ) {
		global $wpdb;

		$defaults = array(
			'count' => 100,
			'type' => 'post',
			'status' => 'publish'
		);

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( !post_type_exists( $type ) ) {
			WP_CLI::warning( "Invalid post type: $type" );
			exit;
		}

		// Get the total number of posts
		$total = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $wpdb->posts WHERE post_type = %s", $type ) );

		$label = get_post_type_object( $type )->labels->singular_name;

		$limit = $count + $total;

		for ( $i = $count; $i < $limit; $i++ ) {
			wp_insert_post( array(
				'post_type' => $type,
				'post_title' =>  "$label $i",
				'post_status' => $status
			) );
		}
	}
}
