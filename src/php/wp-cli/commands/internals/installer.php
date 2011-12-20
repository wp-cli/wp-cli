<?php

require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

// Add the command to the wp-cli
WP_CLI::addCommand( 'installer', 'WP_CLI_Install' );

class WP_CLI_Install extends WP_CLI_Command {
    public function install( $args, $assoc_args ) {
        if ( is_blog_installed() ) {
            WP_CLI::error( 'Wordpress is already installed.' );
            exit( 1 );
        }
        $site_title = $assoc_args["site_title"];
        $username = $assoc_args["username"];
        $admin_email = $assoc_args["email_address"];
        $public = true;
        $admin_password = $assoc_args["password"];
        
        if ( ! $site_title || ! $username || ! $admin_email || ! $admin_password ) {
            WP_CLI::error( 'Missing installation arguments' );
            exit( 1 );
        }
        
        $result = wp_install( $site_title, $username, $admin_email, $public, '', $admin_password );
        if ( is_wp_error( $result ) ) {
            WP_CLI::error( 'Installation failed (' . WP_CLI::errorToString($result) . ').' );
        } else {
            WP_CLI::success( 'WordPress installed successfully.' );
        }
    }

    public function is_installed( $filename ) {
        if ( is_blog_installed() ) {
            exit( 0 );
        } else {
            exit( 1 );
        }
    }
    
    public static function help() {
        WP_CLI::line( <<<EOB
usage: wp installer install --site_title=<site-title> --username=<username> --password=<password> --email_address=<email-address>
   or: wp installer is_installed
EOB
        );
    }
}

