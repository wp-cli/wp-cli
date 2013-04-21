<?php

class Import_Command extends WP_CLI_Command {

	/**
	 * Import content.
	 *
	 * @synopsis <file> [--authors=<authors>] [--skip=<data-type>]
	 */
	public function __invoke( $args, $assoc_args ) {

		list( $file ) = $args;

		if ( ! file_exists( $file ) )
			WP_CLI::error( "File to import doesn't exist." );

		$defaults = array(
			'type'                   => 'wxr',
			'authors'                => null,
			'skip'                   => array(),
			);
		$assoc_args = wp_parse_args( $assoc_args, $defaults );

		$assoc_args['file'] = $file;

		if ( ! empty( $assoc_args['skip'] ) )
			$assoc_args['skip'] = explode( ',', $assoc_args['skip'] );

		$importer = $this->is_importer_available( $assoc_args['type'] );
		if ( is_wp_error( $importer ) )
			WP_CLI::error( $importer->get_error_message() );

		$ret = $this->import( $assoc_args );

		if ( is_wp_error( $ret ) )
			WP_CLI::error( $ret->get_error_message() );
		else
			WP_CLI::success( "Import complete." );
	}

	/**
	 * Import a file into WordPress.
	 */
	private function import( $args ) {

		switch( $args['type'] ) {
			case 'wxr':
			case 'wordpress':
				$ret = $this->import_wxr( $args );
				break;
			default:
				$ret = new WP_Error( 'missing-import-type', "Import type doesn't exist." );
				break;
		}
		return $ret;
	}

	/**
	 * Import a WXR file
	 */
	private function import_wxr( $args ) {

		$wp_import = new WP_Import;

		$wp_import->fetch_attachments = ( in_array( 'attachment', $args['skip'] ) ) ? false : true;
		
		$_GET = array( 'import' => 'wordpress', 'step' => 2 );
		$author_in = $user_select = array();
		// @todo properly handle mapping
		foreach( array() as $in => $out ) {
			$author_in[] = sanitize_user( $in, true );
			$user_select[] = $out;
		}
		$_POST = array(
			'imported_authors'     => $author_in,
			'user_map'             => $user_select,
			'fetch_attachments'    => $wp_import->fetch_attachments,
		);
		$wp_import->import( $args['file'] );

		return true;
	}

	/**
	 * Is the requested importer available?
	 */
	private function is_importer_available( $importer ) {

		require_once ABSPATH . 'wp-admin/includes/plugin.php';

		switch ( $importer ) {
			case 'wxr':
			case 'wordpress':
				if ( class_exists( 'WP_Import' ) ) {
					$ret = true;
				} else {
					$plugins = get_plugins();
					$wordpress_importer = 'wordpress-importer/wordpress-importer.php';
					if ( array_key_exists( $wordpress_importer, $plugins ) )
						$error_msg = "WordPress Importer needs to be activated. Try 'wp plugin activate wordpress-importer'.";
					else
						$error_msg = "WordPress Importer needs to be installed. Try 'wp plugin install wordpress-importer --activate'.";
					$ret = new WP_Error( 'importer-missing', $error_msg );
				}
				break;
			default:
				$ret = new WP_Error( 'missing-import-type', "Import type doesn't exist." );
				break;
		}
		return $ret;
	}


}

WP_CLI::add_command( 'import', new Import_Command );