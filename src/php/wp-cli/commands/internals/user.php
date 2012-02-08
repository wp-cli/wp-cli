<?php

WP_CLI::addCommand('user', 'UserCommand');

/**
 * Implement user command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class UserCommand extends WP_CLI_Command {
  /**
   * List users
   * 
   * @param array $args
	 * @param array $assoc_args
   **/
  public function all( $args, $assoc_args ) {
    global $blog_id;

    $table = new \cli\Table();
    $users = get_users("blog_id=$blog_id&fields=all_with_meta");
    $fields = array('ID', 'user_login', 'display_name', 'user_email',
      'user_registered');

    $table->setHeaders( array_merge($fields, array('roles')) );

    foreach ( $users as $user ) {
      $line = array();

      foreach ( $fields as $field ) {
        $line[] = $user->$field;
      }
      $line[] = implode( ',', $user->roles );

      $table->addRow($line);
    }

    $table->display();

    WP_CLI::line( 'Total: ' . count($users) . ' users' );
  }

	/**
	 * Create a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function create( $args, $assoc_args ) {
		global $blog_id;

    $user_login = $args[0];
    $user_email = $args[1];

    if ( ! $user_login || ! $user_email ) {
      WP_CLI::error("Login and email required (see 'wp user help').");
    }

		$defaults = array(
			'role' => get_option('default_role'),
      'user_pass' => wp_generate_password(),
      'user_registered' => strftime( "%F %T", time() ),
		);

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( 'none' == $role ) {
			$role = false;
		} elseif ( is_null( get_role( $role ) ) ) {
			WP_CLI::warning( "invalid role." );
			exit;
		}

    $user_id = wp_insert_user( array(
      'user_email' => $user_email,
      'user_login' => $user_login,
      'user_pass' => $user_pass,
      'user_registered' => $user_registered,
      'display_name' => $display_name,
      'role' => $role,
    ) );

    if ( is_wp_error($user_id) ) {
      WP_CLI::error( $user_id->get_error_message() );
    } else {
      if ( false === $role ) {
        delete_user_option( $user_id, 'capabilities' );
        delete_user_option( $user_id, 'user_level' );
      }
    }

    WP_CLI::line( "Created user $user_id" );
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp user all
   or: wp user create <user_login> <user_email> [--role=<default_role>]
EOB
	);
	}
}
