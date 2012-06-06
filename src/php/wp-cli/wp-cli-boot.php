<?php

if ( version_compare( PHP_VERSION, '5.3.0', '<' ) ) {
    printf( "Error: wp-cli requires PHP %s or newer. You are running version %s.\n", '5.3.0', PHP_VERSION );
	die(-1);
}

include dirname(__FILE__) . '/wp-cli.php';

