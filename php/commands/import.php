<?php

class Import_Command extends WP_CLI_Command {

	/**
	 * Import content.
	 *
	 * @synopsis <file> --authors=<authors> [--skip=<data-type>]
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
		$import_data = $wp_import->parse( $args['file'] );
		if ( is_wp_error( $import_data ) )
			return $import_data;

		// Prepare the data to be used in process_author_mapping();
		$wp_import->get_authors_from_import( $import_data );
		$author_data = array();
		foreach( $wp_import->authors as $wxr_author ) {
			$author = new \stdClass;
			// Always in the WXR
			$author->user_login = $wxr_author['author_login'];
			$author->user_email = $wxr_author['author_email'];

			// Should be in the WXR; no guarantees
			if ( isset( $wxr_author['author_display_name'] ) )
				$author->display_name = $wxr_author['author_display_name'];
			if ( isset( $wxr_author['author_first_name'] ) )
				$author->first_name = $wxr_author['author_first_name'];
			if ( isset( $wxr_author['author_last_name'] ) )
				$author->last_name = $wxr_author['author_last_name'];

			$author_data[] = $author;
		}

		// Build the author mapping
		$author_mapping = $this->process_author_mapping( $args['authors'], $author_data );
		if ( is_wp_error( $author_mapping ) )
			return $author_mapping;

		$author_in = wp_list_pluck( $author_mapping, 'old_user_login' );
		$author_out = wp_list_pluck( $author_mapping, 'new_user_login' );
		// $user_select needs to be an array of user IDs
		$user_select = array();
		$invalid_user_select = array();
		foreach( $author_out as $author_login ) {
			$user = get_user_by( 'login', $author_login );
			if ( $user )
				$user_select[] = $user->ID;
			else
				$invalid_user_select[] = $author_login;
		}
		if ( ! empty( $invalid_user_select ) )
			return new WP_Error( 'invalid-author-mapping', sprintf( "These user_logins are invalid: %s", implode( ',', $invalid_user_select ) ) );

		// Drive the import
		$wp_import->fetch_attachments = ( in_array( 'attachment', $args['skip'] ) ) ? false : true;
		$_GET = array( 'import' => 'wordpress', 'step' => 2 );
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

	/**
	 * Process how the authors should be mapped
	 *
	 * @param string            $authors_arg      The `--author` argument originally passed to command
	 * @param array             $author_data      An array of WP_User-esque author objects
	 * @return array|WP_Error   $author_mapping   Author mapping array if successful, WP_Error if something bad happened
	 */
	private function process_author_mapping( $authors_arg, $author_data ) {

		// Provided an author mapping file (method checks validity)
		if ( file_exists( $authors_arg ) )
			return $this->read_author_mapping_file( $authors_arg );

		// Provided a file reference, but the file doesn't yet exist
		if ( false !== stripos( $authors_arg, '.csv' ) )
			return $this->create_author_mapping_file( $authors_arg, $author_data );

		switch( $authors_arg ) {
			// Create authors if they don't yet exist; maybe match on email or user_login
			case 'create':
				return $this->create_authors_for_mapping( $author_data );
				break;
			// Skip any sort of author mapping
			case 'skip':
				return array();
				break;
			default:
				return new WP_Error( 'invalid-argument', "'authors' argument is invalid." );
		}
	}

	/**
	 * Read an author mapping file
	 */
	private function read_author_mapping_file( $file ) {

		$author_mapping = array();
		foreach ( new \WP_CLI\Iterators\CSV( $file ) as $i => $author ) {
			if ( ! array_key_exists( 'old_user_login', $author ) || ! array_key_exists( 'new_user_login', $author ) )
				return new WP_Error( 'invalid-author-mapping', "Author mapping file isn't properly formatted." );

			$author_mapping[] = $author;
		}
		return $author_mapping;
	}

	/**
	 * Create an author mapping file, based on provided author data
	 *
	 * @return WP_Error      The file was just now created, so some action needs to be taken
	 */
	private function create_author_mapping_file( $file, $author_data ) {

		if ( touch( $file ) ) {
			$author_mapping = array();
			foreach( $author_data as $author ) {
				$author_mapping[] = array(
						'old_user_login' => $author->user_login,
						'new_user_login' => $this->suggest_user( $author->user_login, $author->user_email ),
					);
			}
			$file_resource = fopen( $file, 'w' );
			\WP_CLI\utils\write_csv( $file_resource, $author_mapping, array( 'old_user_login', 'new_user_login' ) );
			return new WP_Error( 'author-mapping-error', sprintf( "Please update author mapping file before continuing: %s", $file ) );
		} else {
			return new WP_Error( 'author-mapping-error', "Couldn't create author mapping file." );
		}
	}

	/**
	 * Create users if they don't exist, and build an author mapping file
	 */
	private function create_authors_for_mapping( $author_data ) {

		$author_mapping = array();
		foreach( $author_data as $author ) {

			if ( isset( $author->user_email ) ) {
				if ( $user = get_user_by( 'email', $author->user_email ) ) {
					$author_mapping[] = array(
							'old_user_login' => $author->user_login,
							'new_user_login' => $user->user_login,
						);
					continue;
				}
			}

			if ( $user = get_user_by( 'login', $author->user_login ) ) {
				$author_mapping[] = array(
					'old_user_login' => $author->user_login,
					'new_user_login' => $user->user_login,
				);
				continue;
			}

			$user = array(
					'user_login'       => '',
					'user_email'       => '',
					'user_pass'        => wp_generate_password(),
				);
			$user = array_merge( $user, (array)$author );
			$user_id = wp_insert_user( $user );
			if ( is_wp_error( $user_id ) )
				return $user_id;

			$user = get_user_by( 'id', $user_id );
			$author_mapping[] = array(
					'old_user_login' => $author->user_login,
					'new_user_login' => $user->user_login,
				);
		}
		return $author_mapping;

	}

	/**
	 * Suggest a blog user based on the levenshtein distance
	 */
	private function suggest_user( $author_user_login, $author_user_email = '' ) {

		if ( ! isset( $this->blog_users ) )
			$this->blog_users = get_users();

		$shortest = -1;
		$shortestavg = array();
	
		$threshold = floor( ( strlen( $author_user_login ) / 100 ) * 10 ); // 10 % of the strlen are valid
		$closest = '';
		foreach ( $this->blog_users as $user ) {
			// Before we resort to an algorithm, let's try for an exact match
			if ( $author_user_email && $user->user_email == $author_user_email )
				return $user->user_login;

			$levs[] = levenshtein( $author_user_login, $user->display_name );
			$levs[] = levenshtein( $author_user_login, $user->user_login );
			$levs[] = levenshtein( $author_user_login, $user->user_email );
			$levs[] = levenshtein( $author_user_login, array_shift( explode( "@", $user->user_email ) ) );
			arsort( $levs );
			$lev = array_pop( $levs );
			if ( 0 == $lev ) {
				$closest = $user->user_login;
				$shortest = 0;
				break;
			}
	
			if ( ( $lev <= $shortest || $shortest < 0 ) && $lev <= $threshold ) {
				$closest  = $user->user_login;
				$shortest = $lev;
			}
			$shortestavg[] = $lev;
		}	
		// in case all usernames have a common pattern
		if ( $shortest > ( array_sum( $shortestavg ) / count( $shortestavg ) ) )
			return '';
		return $closest;
	}

}

WP_CLI::add_command( 'import', new Import_Command );