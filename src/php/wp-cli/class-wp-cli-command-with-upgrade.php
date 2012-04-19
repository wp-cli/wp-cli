<?php

class WP_CLI_Command_With_Upgrade extends WP_CLI_Command {

	protected $default_subcommand = 'status';

	/**
	 * Update a plugin/theme
	 *
	 * @param array $args
	 * @param array $assoc_args
	 */
	function update( $args, $assoc_args ) {
		call_user_func( $this->upgrade_refresh );

		if ( !empty( $args ) && !isset( $assoc_args['all'] ) ) {
			list( $file, $name ) = $this->parse_name( $args, __FUNCTION__ );

			WP_CLI::get_upgrader( $this->upgrader )->upgrade( $name );
		} else {
			$this->update_multiple( $args, $assoc_args );
		}
	}

	private function update_multiple( $args, $assoc_args ) {
		// Grab all items that need updates
		// If we have no sub-arguments, add them to the output list.
		$item_list = "Available {$this->item_type} updates:";
		$items_to_update = array();
		foreach ( $this->get_item_list() as $file ) {
			if ( $this->get_update_status( $file ) ) {
				$items_to_update[] = $file;

				if ( empty( $assoc_args ) ) {
					if ( false === strpos( $file, '/' ) )
						$name = str_replace('.php', '', basename($file));
					else
						$name = dirname($file);

					$item_list .= "\n\t%y$name%n";
				}
			}
		}

		if ( empty( $items_to_update ) ) {
			WP_CLI::line( "No {$this->item_type} updates available." );
			return;
		}

		// If --all, UPDATE ALL THE THINGS
		if ( isset( $assoc_args['all'] ) ) {
			$upgrader = WP_CLI::get_upgrader( $this->upgrader );
			$result = $upgrader->bulk_upgrade( $items_to_update );

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

		// Else list items that require updates
		} else {
			WP_CLI::line( $item_list );
		}
	}

	/**
	 * Check whether an item has an update available or not.
	 *
	 * @param string $item The plugin/theme theme path
	 *
	 * @return bool
	 */
	protected function get_update_status( $file ) {
		$update_list = get_site_transient( $this->upgrade_transient );

		return isset( $update_list->response[ $file ] );
	}

	/**
	 * Install a plugin/theme from a ZIP file
	 *
	 * @param string $file
	 * @param bool $activate
	 */
	protected function maybe_install_from_zip( $file, $activate ) {
		if ( '.zip' != substr( $file, -4 ) )
			return;

		$file_upgrader = WP_CLI::get_upgrader( $this->upgrader );

		if ( $file_upgrader->install( $file ) ) {
			$slug = $file_upgrader->result['destination_name'];

			if ( $activate )
				$this->activate( array( $slug ) );
		}

		exit;
	}
}
