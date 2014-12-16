<?php

namespace WP_CLI;

/**
 * A Core Upgrader class that leaves packages intact by default.
 *
 * @package wp-cli
 */
class NonDestructiveCoreUpgrader extends CoreUpgrader {
	function unpack_package($package, $delete_package = false) {
		return parent::unpack_package( $package, $delete_package );
	}
}

