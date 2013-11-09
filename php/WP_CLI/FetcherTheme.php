<?php

namespace WP_CLI;

class FetcherTheme extends Fetcher {

	protected $msg = "The '%s' theme could not be found.";

	public function get( $name ) {
		$theme = wp_get_theme( $name );

		if ( !$theme->exists() ) {
			return false;
		}

		return $theme;
	}
}

