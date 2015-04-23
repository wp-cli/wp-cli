<?php

namespace WP_CLI;

abstract class CommandWithUpgrade extends \WP_CLI_Command {

	protected $item_type;
	protected $obj_fields;

	protected $upgrade_refresh;
	protected $upgrade_transient;

	function __construct() {
		// After updating plugins/themes also update translations by running the `core language update` command.
		add_action( 'upgrader_process_complete', function() {
			remove_action( 'upgrader_process_complete', array( 'Language_Pack_Upgrader', 'async_upgrade' ), 20 );
			\WP_CLI::run_command( array( 'core', 'language', 'update' ), array( 'dry-run' => false ) );
		}, 1 );
	}

	abstract protected function get_upgrader_class( $force );

	abstract protected function get_item_list();

	/**
	 * @param array List of update candidates
	 * @param array List of item names
	 * @return array List of update candidates
	 */
	abstract protected function filter_item_list( $items, $args );

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
		\WP_CLI::log( sprintf( _n(
			"%d installed {$this->item_type}:",
			"%d installed {$this->item_type}s:",
		$n ), $n ) );

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

		\WP_CLI::line( 'Legend: ' . \WP_CLI::colorize( implode( ', ', $legend_line ) ) );
	}

	function install( $args, $assoc_args ) {

		foreach ( $args as $slug ) {
			$local_or_remote_zip_file = false;
			$result = false;

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

			if ( $local_or_remote_zip_file ) {
				// Install from local or remote zip file
				$file_upgrader = $this->get_upgrader( $assoc_args );

				if ( $file_upgrader->install( $local_or_remote_zip_file ) ) {
					$slug = $file_upgrader->result['destination_name'];
					$result = true;
				}
			} else {
				// Assume a plugin/theme slug from the WordPress.org repository has been specified
				$result = $this->install_from_repo( $slug, $assoc_args );

				if ( is_wp_error( $result ) ) {
					\WP_CLI::warning( "$slug: " . $result->get_error_message() );
				}
			}

			if ( $result ) {
				if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'activate-network' ) ) {
					\WP_CLI::log( "Network-activating '$slug'..." );
					$this->activate( array( $slug ), array( 'network' => true ) );
				}

				if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'activate' ) ) {
					\WP_CLI::log( "Activating '$slug'..." );
					$this->activate( array( $slug ) );
				}
			}
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

		// WordPress.org forces https, but still sometimes returns http
		// See https://twitter.com/nacin/status/512362694205140992
		$response->download_link = str_replace( 'http://', 'https://', $response->download_link );

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
			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $response_code ) {
				\WP_CLI::error( sprintf(
					"Can't find the requested %s's version %s in the WordPress.org %s repository (HTTP code %d).",
					$download_type, $version, $download_type, $response_code ) );
			}
		}
	}

	protected function get_upgrader( $assoc_args ) {
		$upgrader_class = $this->get_upgrader_class( \WP_CLI\Utils\get_flag_value( $assoc_args, 'force' ) );
		return \WP_CLI\Utils\get_upgrader( $upgrader_class );
	}

	protected function update_many( $args, $assoc_args ) {
		call_user_func( $this->upgrade_refresh );

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) && empty( $args ) ) {
			\WP_CLI::error( "Please specify one or more {$this->item_type}s, or use --all." );
		}

		$items = $this->get_item_list();

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) ) {
			$items = $this->filter_item_list( $items, $args );
		}

		$items_to_update = wp_list_filter( $items, array(
			'update' => true
		) );

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run' ) ) {
			if ( empty( $items_to_update ) ) {
				\WP_CLI::line( "No {$this->item_type} updates available." );
				return;
			}

			\WP_CLI::line( "Available {$this->item_type} updates:" );

			\WP_CLI\Utils\format_items( 'table', $items_to_update,
				array( 'name', 'status', 'version', 'update_version' ) );

			return;
		}

		$result = array();

		// Only attempt to update if there is something to update
		if ( !empty( $items_to_update ) ) {
			$cache_manager = \WP_CLI::get_http_cache_manager();
			foreach ($items_to_update as $item) {
				$cache_manager->whitelist_package($item['update_package'], $this->item_type, $item['name'], $item['update_version']);
			}
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

		if ($num_to_update > 0) {
			$status = array();
			foreach($items_to_update as $item_to_update => $info) {
				$status[$item_to_update] = array(
					'name' => $info['name'],
					'old_version' => $info['version'],
					'new_version' => $info['update_version'],
					'status' => $result[$item_to_update] !== null ? 'Updated' : 'Error',
				);
			}
			\WP_CLI\Utils\format_items( 'table', $status,
				array( 'name', 'old_version', 'new_version', 'status' ) );
		}
	}

	protected function _list( $_, $assoc_args ) {
		// Force WordPress to check for updates
		call_user_func( $this->upgrade_refresh );

		$all_items = $this->get_all_items();
		if ( !is_array( $all_items ) )
			\WP_CLI::error( "No {$this->item_type}s found." );

		foreach ( $all_items as $key => &$item ) {

			if ( empty( $item['version'] ) )
				$item['version'] = '';

			foreach ( $item as $field => &$value ) {
				if ( $value === true ) {
					$value = 'available';
				} else if ( $value === false ) {
					$value = 'none';
				}
			}

			foreach ( $this->obj_fields as $field ) {
				if ( isset( $assoc_args[$field] )
					&& $assoc_args[$field] != $item[$field] )
					unset( $all_items[$key] );
			}
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $all_items );
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

	/**
	 * Get the available update info
	 *
	 * @param string $slug The plugin/theme slug
	 *
	 * @return array|null
	 */
	protected function get_update_info( $slug ) {
		$update_list = get_site_transient( $this->upgrade_transient );

		if ( !isset( $update_list->response[ $slug ] ) )
			return null;

		return (array) $update_list->response[ $slug ];
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
	 * Search wordpress.org repo.
	 *
	 * @param  array $args       A arguments array containing the search term in the first element.
	 * @param  array $assoc_args Data passed in from command.
	 */
	protected function _search( $args, $assoc_args ) {
		$term = $args[0];

		$defaults = array(
			'per-page' => 10,
			'fields' => array( 'name', 'slug', 'rating' )
		);
		$assoc_args = array_merge( $defaults, $assoc_args );

		$formatter = $this->get_formatter( $assoc_args );

		$api_args = array(
			'per_page' => (int) $assoc_args['per-page'],
			'search' => $term,
		);

		if ( 'plugin' == $this->item_type ) {
			$api = plugins_api( 'query_plugins', $api_args );
		} else {
			$api = themes_api( 'query_themes', $api_args );
		}

		if ( is_wp_error( $api ) )
			\WP_CLI::error( $api->get_error_message() . __( ' Try again' ) );

		$plural = $this->item_type . 's';

		if ( ! isset( $api->$plural ) )
			\WP_CLI::error( __( 'API error. Try Again.' ) );

		$items = $api->$plural;

		$count = \WP_CLI\Utils\get_flag_value( $api->info, 'results', 'unknown' );
		\WP_CLI::success( sprintf( 'Showing %s of %s %s.', count( $items ), $count, $plural ) );

		$formatter->display_items( $items );
	}

	protected function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields, $this->item_type );
	}
}

