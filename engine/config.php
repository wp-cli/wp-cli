<?php

define('WP_ROOT', '../wordpress/');

// Taken from https://github.com/88mph/wpadmin/blob/master/wpadmin.php

// Does the user have access to read the directory? If so, allow them to use the
// command line tool.

if(true == is_readable(WP_ROOT . 'wp-load.php')){
    // Load WordPress libs.
    require_once(WP_ROOT . 'wp-load.php');
    require_once(ABSPATH . WPINC . '/template-loader.php');
    require_once(ABSPATH . 'wp-admin/includes/admin.php');
}
else {
    die("Either this is not a WordPress document root or you do not have permission to administer this site. \n");
}