<?php

namespace WP_CLI;

abstract class CommandWithUpgrade extends \WP_CLI_Command {

	protected $item_type;
	protected $upgrade_refresh;
	protected $upgrade_transient;

	abstract protected function get_upgrader_class( $force );

	abstract protected function get_item_list();
	abstract protected function get_all_items();

	abstract protected function get_status( $file );

	abstract protected function status_single( $args );

	abstract protected function install_from_repo( $slug, $assoc_args );

	function status( $args ) {
		// Force WordPress to check for updates
		call_user_func( $this->upgrade_refresh );

		if ( empty( $args ) ) {
			$this->status_all();
		} else {
			$this->status_single( $args );
		}
	}

	private function status_all() {
		$items = $this->get_all_items();

		$n = count( $items );

		// Not interested in the translation, just the number logic
		\WP_CLI::log( sprintf( _n( "%d installed {$this->item_type}:", "%d installed {$this->item_type}s:", $n ), $n ) );

		$padding = $this->get_padding($items);

		foreach ( $items as $file => $details ) {
			if ( $details['update'] ) {
				$line = ' %yU%n';
			} else {
				$line = '  ';
			}

			$line .= $this->format_status( $details['status'], 'short' );
			$line .= " " . str_pad( $details['name'], $padding ). "%n";
			if ( !empty( $details['version'] ) ) {
				$line .= " " . $details['version'];
			}

			\WP_CLI::line( \WP_CLI::colorize( $line ) );
		}

		\WP_CLI::line();

		$this->show_legend( $items );
	}

	private function get_padding( $items ) {
		$max_len = 0;

		foreach ( $items as $details ) {
			$len = strlen( $details['name'] );

			if ( $len > $max_len ) {
				$max_len = $len;
			}
		}

		return $max_len;
	}

	private function show_legend( $items ) {
		$statuses = array_unique( wp_list_pluck( $items, 'status' ) );

		$legend_line = array();

		foreach ( $statuses as $status ) {
			$legend_line[] = sprintf( '%s%s = %s%%n',
				$this->get_color( $status ),
				$this->map['short'][ $status ],
				$this->map['long'][ $status ]
			);
		}

		if ( in_array( true, wp_list_pluck( $items, 'update' ) ) )
			$legend_line[] = '%yU = Update Available%n';

		\WP_CLI::line( 'Legend: ' . implode( ', ', \WP_CLI::colorize( $legend_line ) ) );
	}

	function install( $args, $assoc_args ) {
		// Force WordPress to check for updates
		call_user_func( $this->upgrade_refresh );

		$slug = stripslashes( $args[0] );

		$local_or_remote_zip_file = '';

		// Check if a URL to a remote zip file has been specified
		$url_path = parse_url( $slug, PHP_URL_PATH );
		if ( ! empty( $url_path ) && '.zip' === substr( $url_path, - 4 ) ) {
			$local_or_remote_zip_file = $slug;
		} else {
			// Check if a local zip file has been specified
			if ( 'zip' === pathinfo( $slug, PATHINFO_EXTENSION ) && file_exists( $slug ) ) {
				$local_or_remote_zip_file = $slug;
			}
		}

		if ( ! empty( $local_or_remote_zip_file ) ) {

			// Install from local or remote zip file
			$file_upgrader = $this->get_upgrader( $assoc_args );

			if ( $file_upgrader->install( $local_or_remote_zip_file ) ) {
				$slug = $file_upgrader->result['destination_name'];

				if ( isset( $assoc_args['activate'] ) ) {
					\WP_CLI::log( "Activating '$slug'..." );
					$this->activate( array( $slug ) );
				}
			} else {
				exit(1);
			}

		} else {
			// Assume a plugin/theme slug from the WordPress.org repository has been specified
			$this->install_from_repo( $slug, $assoc_args );
		}
	}

	/**
	 * Prepare an API response for downloading a particular version of an item.
	 *
	 * @param object $response wordpress.org API response
	 * @param string $version The desired version of the package
	 */
	protected static function alter_api_response( $response, $version ) {
		if ( $response->version == $version )
			return;

		list( $link ) = explode( $response->slug, $response->download_link );

		if ( false !== strpos( $response->download_link, 'theme' ) )
			$download_type = 'theme';
		else
			$download_type = 'plugin';

		if ( 'dev' == $version ) {
			$response->download_link = $link . $response->slug . '.zip';
			$response->version = 'Development Version';
		} else {
			$response->download_link = $link . $response->slug . '.' . $version .'.zip';
			$response->version = $version;

			// check if the requested version exists
			$response = wp_remote_head( $response->download_link );
			if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
				\WP_CLI::error( sprintf(
					"Can't find the requested %s's version %s in the WordPress.org %s repository.",
					$download_type, $version, $download_type ) );
			}
		}
	}

	protected function get_upgrader( $assoc_args ) {
		$upgrader_class = $this->get_upgrader_class( isset( $assoc_args['force'] ) );
		return \WP_CLI\Utils\get_upgrader( $upgrader_class );
	}

	function update_all( $args, $assoc_args ) {
		call_user_func( $this->upgrade_refresh );

		$items_to_update = wp_list_filter( $this->get_item_list(), array(
			'update' => true
		) );

		if ( isset( $assoc_args['dry-run'] ) ) {
			$item_list = "Available {$this->item_type} updates:";

			if ( empty( $items_to_update ) ) {
				$item_list .= " none";
			} else {
				foreach ( $items_to_update as $file => $details ) {
					$item_list .= "\n\t%y" . $details['name'] . "%n";
				}
			}

			\WP_CLI::line( $item_list );
			return;
		}

		$result = array();

		// Only attempt to update if there is something to update
		if ( !empty( $items_to_update ) ) {
			$upgrader = $this->get_upgrader( $assoc_args );
			$result = $upgrader->bulk_upgrade( wp_list_pluck( $items_to_update, 'update_id' ) );
		}

		// Let the user know the results.
		$num_to_update = count( $items_to_update );
		$num_updated = count( array_filter( $result ) );

		$line = "Updated $num_updated/$num_to_update {$this->item_type}s.";

		if ( $num_to_update == $num_updated ) {
			\WP_CLI::success( $line );
		} else if ( $num_updated > 0 ) {
			\WP_CLI::warning( $line );
		} else {
			\WP_CLI::error( $line );
		}
	}

	protected function _list( $_, $format ) {
		$values = array(
			'format' => 'table',
			'fields' => $this->fields
		);

		foreach ( $values as $key => &$value ) {
			if ( isset( $format[ $key ] ) ) {
				$value = $format[ $key ];
				unset( $format[ $key ] );
			}
		}
		unset( $value );

		$all_items = $this->get_all_items();
		$items = $this->create_objects( $all_items );

		\WP_CLI\Utils\format_items( $values['format'], $items, $values['fields'] );
	}

	/**
	 * Check whether an item has an update available or not.
	 *
	 * @param string $slug The plugin/theme slug
	 *
	 * @return bool
	 */
	protected function has_update( $slug ) {
		$update_list = get_site_transient( $this->upgrade_transient );

		return isset( $update_list->response[ $slug ] );
	}

	private $map = array(
		'short' => array(
			'inactive' => 'I',
			'active' => 'A',
			'active-network' => 'N',
			'must-use' => 'M',
		),
		'long' => array(
			'inactive' => 'Inactive',
			'active' => 'Active',
			'active-network' => 'Network Active',
			'must-use' => 'Must Use',
		)
	);

	private function create_objects( $items ) {
		if ( !is_array( $items ) && !empty( $items ) )
			\WP_CLI::error( sprintf( "No '$this->item_type's found." ) );

		$objects = array();

		foreach ( $items as $item ) {
			$object = new \stdClass;

			if ( empty( $item['version'] ) )
				$item['version'] = "";

			foreach ( $item as $field => $value ) {
				if ( $value === true ) {
					$value = "available";
				} else if ( $value === false) {
					$value = "none";
				}

				$object->{$field} = $value;
			}
			$objects[] = $object;
		}

		return $objects;
	}

	protected function format_status( $status, $format ) {
		return $this->get_color( $status ) . $this->map[ $format ][ $status ];
	}

	private function get_color( $status ) {
		static $colors = array(
			'inactive' => '',
			'active' => '%g',
			'active-network' => '%g',
			'must-use' => '%c',
		);

		return $colors[ $status ];
	}

	/**
	 * Search wordpress.org plugin repo
	 *
	 * @param  object $api       data from WP plugin/theme API
	 * @param  array  $fields    Data fields to display in table.
	 * @param  string $data_type Plugin or Theme api endpoint
	 */
	public function _search( $api, $fields, $data_type = 'plugin' ) {

		// Sanitize to 1 of 2 types
		$data_type = 'plugin' === $data_type ? 'plugin' : 'theme';
		$plural = $data_type . 's';
		$data = $api->$plural;
		$count = isset( $api->info['results'] ) ? $api->info['results'] : count( $data );

		if ( is_wp_error( $api ) )
			\WP_CLI::error( $api->get_error_message() . __( ' Try again' ) );

		if ( ! isset( $data ) )
			\WP_CLI::error( __( 'API error. Try Again.' ) );

		\WP_CLI::success( $count .' '. $plural .' Found. \'search=$key\' in place of slug available for '. $data_type .' commands.' );

		foreach ( $data as $key => $item ) {
			$item->key = $key;
			$data[$key] = $item;
		}

		$set = set_site_transient( 'wpcli-$data_type-search-data', $data, 60*60 );

		\WP_CLI\Utils\format_items( 'table', $data, array_merge( array( 'key' ), $fields ) );

	}

	/**
	 * Parse the name of a plugin to check if 'search=' exists, and check search transient for the key
	 *
	 * @param string name
	 * @return string
	 */
	public function parse_search_key( $name, $data_type = 'plugin' ) {

		// Sanitize to 1 of 2 types
		$data_type = 'plugin' === $data_type ? 'plugin' : 'theme';

		if ( false !== strpos( $name, 'search=' ) ) {
			$search_key = (int) str_replace( 'search=', '', $name );
			if ( ( $trans = get_site_transient( 'wpcli-$data_type-search-data' ) ) && isset( $trans[$search_key] ) )
				$name = $trans[$search_key]->slug;
			else
				\WP_CLI::error( 'There is no recent search with that key.' );
		}
		return $name;
	}

}
