<?php

namespace WP_CLI;

class FetcherPlugin implements Fetcher {

	/**
	 * @param string $name The plugin slug
	 * @return string|false The plugin filename
	 */
	public function get( $name ) {
		$plugins = get_plugins( '/' . $name );

		if ( !empty( $plugins ) ) {
			$file = $name . '/' . key( $plugins );
		} else {
			$file = $name . '.php';

			$plugins = get_plugins();

			if ( !isset( $plugins[$file] ) ) {
				return false;
			}
		}

		return $file;
	}

	public function get_check( $name ) {
		$file = $this->get( $name );

		if ( ! $file ) {
			\WP_CLI::error( "The '$name' plugin could not be found." );
		}

		return $file;
	}

	public function get_many( $args ) {
		$plugins = array();

		foreach ( $args as $name ) {
			$file = $this->get( $name );
			if ( $file ) {
				$plugins[] = (object) array(
					'name' => $name,
					'file' => $file
				);
			} else {
				\WP_CLI::warning( "The '$name' plugin could not be found." );
			}
		}

		return $plugins;
	}
}

