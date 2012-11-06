<?php

abstract class WP_CLI_Command_With_Upgrade extends WP_CLI_Command {

	public static function get_default_subcommand() {
		return 'status';
	}

	protected $item_type;
	protected $upgrader;
	protected $upgrade_refresh;
	protected $upgrade_transient;

	abstract protected function parse_name( $args );

	abstract protected function get_item_list();
	abstract protected function get_all_items();

	abstract protected function get_status( $file );
	abstract protected function get_details( $file );

	abstract protected function _status_single( $details, $name, $version, $status );

	abstract protected function install_from_repo( $slug, $assoc_args );

	function status( $args ) {
		// Force WordPress to check for updates
		call_user_func( $this->upgrade_refresh );

		if ( empty( $args ) ) {
			$this->status_all();
		} else {
			list( $file, $name ) = $this->parse_name( $args );

			$this->status_single( $file, $name );
		}
	}

	private function status_all() {
		$items = $this->get_all_items();

		$n = count( $items );

		// Not interested in the translation, just the number logic
		WP_CLI::line( sprintf( _n( "%d installed {$this->item_type}:", "%d installed {$this->item_type}s:", $n ), $n ) );

		foreach ( $items as $file => $details ) {
			if ( $details['update'] ) {
				$line = ' %yU%n';
			} else {
				$line = '  ';
			}

			$line .= $this->format_status( $details['status'], 'short' );
			$line .= " " . $details['name'] . "%n";

			WP_CLI::line( $line );
		}

		WP_CLI::line();

		$this->show_legend( $items );
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

		WP_CLI::line( 'Legend: ' . implode( ', ', $legend_line ) );
	}

	private function status_single( $file, $name ) {
		$details = $this->get_details( $file );

		$status = $this->format_status( $this->get_status( $file ), 'long' );

		$version = $details[ 'Version' ];

		if ( $this->has_update( $file ) )
			$version .= ' (%gUpdate available%n)';

		$this->_status_single( $details, $name, $version, $status );
	}

	function install( $args, $assoc_args ) {
		if ( empty( $args ) ) {
			WP_CLI::line( "usage: wp $this->item_type install <slug>" );
			exit;
		}

		// Force WordPress to check for updates
		call_user_func( $this->upgrade_refresh );

		$slug = stripslashes( $args[0] );

		if ( '.zip' == substr( $slug, -4 ) ) {
			$file_upgrader = WP_CLI\Utils\get_upgrader( $this->upgrader );

			if ( $file_upgrader->install( $slug ) ) {
				$slug = $file_upgrader->result['destination_name'];

				if ( isset( $assoc_args['activate'] ) ) {
					WP_CLI::line( "Activating '$slug'..." );
					$this->activate( array( $slug ) );
				}
			} else {
				exit(1);
			}
		} else {
			$this->install_from_repo( $slug, $assoc_args );
		}
	}

	function update( $args, $assoc_args ) {
		call_user_func( $this->upgrade_refresh );

		list( $file, $name ) = $this->parse_name( $args );

		WP_CLI\Utils\get_upgrader( $this->upgrader )->upgrade( $file );
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

			WP_CLI::line( $item_list );
			return;
		}

		$upgrader = WP_CLI\Utils\get_upgrader( $this->upgrader );
		$result = $upgrader->bulk_upgrade( wp_list_pluck( $items_to_update, 'update_id' ) );

		// Let the user know the results.
		$num_to_update = count( $items_to_update );
		$num_updated = count( array_filter( $result ) );

		$line = "Updated $num_updated/$num_to_update {$this->item_type}s.";

		if ( $num_to_update == $num_updated ) {
			WP_CLI::success( $line );
		} else if ( $num_updated > 0 ) {
			WP_CLI::warning( $line );
		} else {
			WP_CLI::error( $line );
		}
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

	private function format_status( $status, $format ) {
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
}
