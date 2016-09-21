<?php

/**
 * Manage a user's sessions.
 *
 * ## EXAMPLES
 *
 */
class User_Session_Command extends WP_CLI_Command {

	private $fields = array(
		'token',
		'login_time',
		'expiration_time',
		'ip',
		'ua',
	);

	public function __construct() {
		$this->fetcher = new \WP_CLI\Fetchers\User;
	}

	/**
	 * Destroy all sessions for the given user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, user email, or user login.
	 *
	 * ## EXAMPLES
	 *
	 *     # Destroy a user's sessions
	 *     $ wp user session destroy-all admin
	 *     Success: Destroyed all sessions.
	 *
	 * @subcommand destroy-all
	 */
	public function destroy_all( $args, $assoc_args ) {
		$user      = $this->fetcher->get_check( $args[0] );
		$manager   = WP_Session_Tokens::get_instance( $user->ID );
		$sessions  = $manager->destroy_all();

		WP_CLI::success( 'Destroyed all sessions.' );
	}

	/**
	 * List sessions for the given user.
	 *
	 * ## OPTIONS
	 *
	 * <user>
	 * : User ID, user email, or user login.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count, yaml. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each session:
	 *
	 * * token
	 * * login_time
	 * * expiration_time
	 * * ip
	 * * ua
	 *
	 * These fields are optionally available:
	 *
	 * * expiration
	 * * login
	 *
	 * ## EXAMPLES
	 *
	 *     # List a user's sessions
	 *     $ wp user session list admin@example.com --format=csv
	 *     login_time,expiration_time,ip,ua
	 *     "2016-01-01 12:34:56","2016-02-01 12:34:56",127.0.0.1,"Mozilla/5.0..."
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$user      = $this->fetcher->get_check( $args[0] );
		$formatter = $this->get_formatter( $assoc_args );
		$manager   = WP_Session_Tokens::get_instance( $user->ID );
		$sessions  = $this->get_all_sessions( $manager );

		if ( 'ids' == $formatter->format ) {
			echo implode( ' ', array_keys( $sessions ) );
		} else if ( 'count' === $formatter->format ) {
			$formatter->display_items( $sessions );
		} else {
			$formatter->display_items( $sessions );
		}
	}

	protected function get_all_sessions( WP_Session_Tokens $manager ) {
		$get_sessions = new ReflectionMethod( $manager, 'get_sessions' );
		$get_sessions->setAccessible( true );
		$sessions = $get_sessions->invoke( $manager );

		array_walk( $sessions, function( & $session, $token ) {
			$session['token']           = $token;
			$session['login_time']      = date( 'Y-m-d H:i:s', $session['login'] );
			$session['expiration_time'] = date( 'Y-m-d H:i:s', $session['expiration'] );
		} );

		return $sessions;
	}

	private function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->fields );
	}

}

WP_CLI::add_command( 'user session', 'User_Session_Command' );
