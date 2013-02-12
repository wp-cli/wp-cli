<?php

class Import_Command extends WP_CLI_Command {

	/**
	 * Import content from a WXR file
	 *
	 * @synopsis <file> [--author_mapping=<file>] [--skip_attachments=<bool>]
	 */
	public function __invoke( $args, $assoc_args ) {

		list( $file ) = $args;

		$defaults = array(
			'skip_attachments'       => false,
			'author_mapping'         => null,
			);
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$wp_import = new WP_Import;
		$wp_import->fetch_attachments = ( $assoc_args['skip_attachments'] ) ? false : true;

		$_GET = array( 'import' => 'wordpress', 'step' => 2 );
		$author_in = $user_select = array();
		// @todo properly handle mapping
		foreach( array() as $in => $out ) {
			$author_in[] = sanitize_user( $in, true );
			$user_select[] = $out;
		}
		$_POST = array(
			'imported_authors' 	=> $author_in,
			'user_map' 	=> $user_select,
			'fetch_attachments' => $wp_import->fetch_attachments,
			);
		$wp_import->import( $file );

		WP_CLI::success( "Import complete." );
	}


}

WP_CLI::add_command( 'import', new Import_Command );