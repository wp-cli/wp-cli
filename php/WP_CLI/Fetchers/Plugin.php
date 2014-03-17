<?php

namespace WP_CLI\Fetchers;

class Plugin extends Base {

	protected $msg = "The '%s' plugin could not be found.";

	public function get( $name ) {
		foreach ( get_plugins() as $file => $_ ) {
			if ( $file === "$name.php" ||
				( dirname( $file ) === $name && $name !== '.' ) ) {
				return (object) compact( 'name', 'file' );
			}
		}

		return false;
	}
}

