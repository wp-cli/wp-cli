<?php

namespace WP_CLI;

class FetcherPlugin extends Fetcher {

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

		return (object) array(
			'name' => $name,
			'file' => $file
		);
	}
}

