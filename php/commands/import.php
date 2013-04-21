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
		$import_data = $wp_import->parse( $args['file'] );
		if ( is_wp_error( $import_data ) )
			return $import_data;

		$author_in = $user_select = array();
		if ( file_exists( $args['authors'] ) ) {
			foreach ( new \WP_CLI\Iterators\CSV( $args['authors'] ) as $i => $author ) {
				if ( ! array_key_exists( 'old_user_login', $author ) || ! array_key_exists( 'new_user_login', $author ) )
					return new WP_Error( 'invalid-author-mapping', "Author mapping file isn't properly formatted." );

				$author_in[] = $author['old_user_login'];
				$user_select[] = $author['new_user_login'];
			}
		} else if ( false !== stripos( $args['authors'], '.csv' ) ) {
			if ( touch( $args['authors'] ) ) {
				$author_mapping = array();
				foreach( $import_data['authors'] as $wxr_author ) {	
					$author_mapping[] = array(
							'old_user_login' => $wxr_author['author_login'],
							'new_user_login' => $this->suggest_user( $wxr_author['author_login'], $wxr_author['author_email'] ),
						);
				}
				$file = fopen( $args['authors'], 'w' );
				\WP_CLI\utils\write_csv( $file, $author_mapping, array( 'old_user_login', 'new_user_login' ) );
				WP_CLI::success( sprintf( "Please update author mapping file before continuing: %s", $args['authors'] ) );
				exit;
			} else {
				return new WP_Error( 'author-mapping-error', "Couldn't create author mapping file." );
			}
		} else {
			switch( $args['authors'] ) {
				// Create authors if they don't yet exist; maybe match on email or user_login
				case 'create':
					foreach( $import_data['authors'] as $author ) {

						if ( $user = get_user_by( 'email', $author['author_email'] ) ) {
							$author_in[] = $author['author_login'];
							$user_select[] = $user->user_login;
							continue;
						}

						if ( $user = get_user_by( 'login', $author['author_login'] ) ) {
							$author_in[] = $author['author_login'];
							$user_select[] = $user->user_login;
							continue;
						}

						$user = array(
								'user_login'       => $author['author_login'],
								'user_email'       => $author['author_email'],
								'display_name'     => $author['author_display_name'],
								'first_name'       => $author['author_first_name'],
								'last_name'        => $author['author_last_name'],
								'user_pass'        => wp_generate_password(),
							);
						$user_id = wp_insert_user( $user );
						if ( is_wp_error( $user_id ) )
							return $user_id;

						$user = get_user_by( 'id', $user_id );
						$author_in[] = $author['author_login'];
						$user_select[] = $user->user_login;
					}
					break;
				// Skip any sort of author mapping
				case 'skip':
					break;
				default:
					return new WP_Error( 'invalid-argument', "'authors' argument is invalid." );
			}
		}

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