<?php

namespace WP_CLI;

class FetcherTheme extends Fetcher {

	public function get( $name ) {
		$theme = wp_get_theme( $name );

		if ( !$theme->exists() ) {
			return false;
		}

		return $theme;
	}
}

