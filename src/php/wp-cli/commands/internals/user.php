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

    $users = get_users("blog_id=$blog_id");
    $fields = array('ID', 'user_login', 'display_name', 'user_email',
      'user_registered', 'user_status');
    $table = new \cli\Table();
    $table->setHeaders($fields);

    foreach ( $users as $user ) {
      $line = array();

      foreach ( $fields as $field ) {
        $line[] = $user->$field;
      }

      $table->addRow($line);
    }

    $table->display();

    $total = count_users();
    WP_CLI::line( 'Total: ' . $total['total_users'] . ' users' );
  }

	/**
	 * Create a user
	 *
	 * @param array $args
	 * @param array $assoc_args
	 **/
	public function users( $args, $assoc_args ) {
		global $blog_id;

		$defaults = array(
			'count' => 100,
			'role' => get_option('default_role'),
		);

		extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

		if ( 'none' == $role ) {
			$role = false;
		} elseif ( is_null( get_role( $role ) ) ) {
			WP_CLI::warning( "invalid role." );
			exit;
		}

		$user_count = count_users();

		$total = $user_count['total_users'];

		$limit = $count + $total;

		for ( $i = $total; $i < $limit; $i++ ) {
			$login = sprintf( 'user_%d_%d', $blog_id, $i );
			$name = "User $i";

			$user_id = wp_insert_user( array(
				'user_login' => $login,
				'user_pass' => $login,
				'nickname' => $name,
				'display_name' => $name,
				'role' => $role
			) );

			if ( false === $role ) {
				delete_user_option( $user_id, 'capabilities' );
				delete_user_option( $user_id, 'user_level' );
			}
		}
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp generate posts [--count=100] [--type=post] [--status=publish]
   or: wp generate users [--count=100] [--role=<role>]
EOB
	);
	}
}
