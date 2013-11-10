<?php

namespace WP_CLI\Fetchers;

class Plugin extends Base {

	protected $msg = "The '%s' plugin could not be found.";

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

