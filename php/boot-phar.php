<?php

if ( file_exists( 'phar://wp-cli.phar/php/wp-cli.php' ) ) {
	define( 'WP_CLI_ROOT', 'phar://wp-cli.phar' );
	include WP_CLI_ROOT . '/php/wp-cli.php';
} elseif ( file_exists( 'phar://wp-cli.phar/vendor/wp-cli/wp-cli/php/wp-cli.php' ) ) {
	define( 'WP_CLI_ROOT', 'phar://wp-cli.phar/vendor/wp-cli/wp-cli' );
	include WP_CLI_ROOT . '/php/wp-cli.php';
} else {
	echo "Couldn't find 'php/wp-cli.php'. Was this Phar built correctly?";
	exit(1);
}
