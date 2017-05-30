<?php

namespace WP_CLI;

/**
 * Class AutoloadSplitter.
 *
 * This class is used to provide the splitting logic to the
 * `wp-cli/autoload-splitter` Composer plugin.
 *
 * @package WP_CLI
 */
class AutoloadSplitter {

	/**
	 * Check whether the current class should be split out into a separate
	 * autoloader.
	 *
	 * Note: `class` in this context refers to all PHP autoloadable elements:
	 *    - classes
	 *    - interfaces
	 *    - traits
	 *
	 * @param string $class Fully qualified name of the current class.
	 * @param string $code  Path to the code file that declares the class.
	 *
	 * @return bool Whether to split out the class into a separate autoloader.
	 */
	public function __invoke( $class, $code ) {
		return 1 === preg_match( '/.*\/wp-cli\/\w*(?:-\w*)*-command\/.*/', $code )
			|| 1 === preg_match( '/.*\/php\/commands\/src\/.*/', $code );
	}
}
