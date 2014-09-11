<?php

/**
 * Manage WP-Cron events.
 *
 */
class Cron_Event_Command extends WP_CLI_Command {

	private $fields = array(
		'hook',
		'next_run_gmt',
		'next_run_relative',
		'recurrence',
	);
	private static $time_format = 'Y-m-d H:i:s';

	/**
	 * List scheduled cron events.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv, ids. Default: table.
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each cron event:
	 * * hook
	 * * next_run_gmt
	 * * next_run_relative
	 * * recurrence
	 *
	 * These fields are optionally available:
	 * * time
	 * * sig
	 * * args
	 * * schedule
	 * * interval
	 * * next_run
	 *
	 * ## EXAMPLES
	 *
	 *     wp cron event list
	 *
	 *     wp cron event list --fields=hook,next_run --format=json
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$formatter = $this->get_formatter( $assoc_args );

		$events = self::get_cron_events();

		if ( is_wp_error( $events ) ) {
			$events = array();
		}

		if ( in_array( $formatter->format, array( 'table', 'csv' ) ) ) {
			$events = array_map( function( $event ){
				$event->args = json_encode( $event->args );
				return $event;
			}, $events );
		}

		if ( 'ids' == $formatter->format ) {
			echo implode( ' ', wp_list_pluck( $events, 'hook' ) );
		} else {
			$formatter->display_items( $events );
		}

	}

	/**
	 * Schedule a new cron event.
	 *
	 * ## OPTIONS
	 *
	 * <hook>
	 * : The hook name
	 *
	 * [<next-run>]
	 * : A Unix timestamp or an English textual datetime description compatible with `strtotime()`. Defaults to now.
	 *
	 * [<recurrence>]
	 * : How often the event should recur. See `wp cron schedule list` for available schedule names. Defaults to no recurrence.
	 *
	 * [--<field>=<value>]
	 * : Associative args for the event.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cron event schedule cron_test
	 *
	 *     wp cron event schedule cron_test now hourly
	 *
	 *     wp cron event schedule cron_test '+1 hour' --foo=1 --bar=2
	 */
	public function schedule( $args, $assoc_args ) {

		$hook = $args[0];
		$next_run = ( isset( $args[1] ) ) ? $args[1] : 'now';
		$recurrence = ( isset( $args[2] ) ) ? $args[2] : false;

		if ( ! empty( $next_run ) ) {
			$timestamp = time();
		} else if ( is_numeric( $next_run ) ) {
			$timestamp = absint( $next_run );
		} else {
			$timestamp = strtotime( $next_run );
		}

		if ( ! $timestamp ) {
			WP_CLI::error( sprintf( "'%s' is not a valid datetime.", $next_run ) );
		}

		if ( ! empty( $recurrence ) ) {

			$schedules = wp_get_schedules();

			if ( ! isset( $schedules[$recurrence] ) ) {
				WP_CLI::error( sprintf( "'%s' is not a valid schedule name for recurrence.", $recurrence ) );
			}

			$event = wp_schedule_event( $timestamp, $recurrence, $hook, $assoc_args );

		} else {

			$event = wp_schedule_single_event( $timestamp, $hook, $assoc_args );

		}

		if ( false !== $event ) {
			WP_CLI::success( sprintf( "Scheduled event with hook '%s' for %s GMT.", $hook, date( self::$time_format, $timestamp ) ) );
		} else {
			WP_CLI::error( 'Event not scheduled' );
		}

	}

	/**
	 * Run the next scheduled cron event for the given hook.
	 *
	 * ## OPTIONS
	 *
	 * <hook>
	 * : The hook name
	 */
	public function run( $args, $assoc_args ) {

		$hook   = $args[0];
		$events = self::get_cron_events();

		if ( is_wp_error( $events ) ) {
			WP_CLI::error( $events );
		}

		$executed = 0;
		foreach ( $events as $id => $event ) {
			if ( $event->hook == $hook ) {
				$result = self::run_event( $event );
				if ( $result ) {
					$executed++;
				} else {
					WP_CLI::warning( sprintf( "Failed to the execute the cron event '%s'", $hook ) );
				}
			}
		}

		if ( $executed ) {
			$message = ( 1 == $executed ) ? "Executed the cron event '%2\$s'" : "Executed %1\$d instances of the cron event '%2\$s'";
			WP_CLI::success( sprintf( $message, $executed, $hook ) );
		} else {
			WP_CLI::error( sprintf( "Invalid cron event '%s'", $hook ) );
		}

	}

	/**
	 * Executes an event immediately.
	 *
	 * @param stdClass $event The event
	 * @return bool Whether the event was successfully executed or not.
	 */
	protected static function run_event( stdClass $event ) {

		if ( ! defined( 'DOING_CRON' ) ) {
			define( 'DOING_CRON', true );
		}

		if ( $event->schedule != false ) {
			$new_args = array( $event->time, $event->schedule, $event->hook, $event->args );
			call_user_func_array( 'wp_reschedule_event', $new_args );
		}

		wp_unschedule_event( $event->time, $event->hook, $event->args );

		do_action_ref_array( $event->hook, $event->args );

		return true;

	}

	/**
	 * Delete the next scheduled cron event for the given hook.
	 *
	 * ## OPTIONS
	 *
	 * <hook>
	 * : The hook name
	 */
	public function delete( $args, $assoc_args ) {

		$hook   = $args[0];
		$events = self::get_cron_events();

		if ( is_wp_error( $events ) ) {
			WP_CLI::error( $events );
		}

		$deleted = 0;
		foreach ( $events as $id => $event ) {
			if ( $event->hook == $hook ) {
				$result = self::delete_event( $event );
				if ( $result ) {
					$deleted++;
				} else {
					WP_CLI::warning( sprintf( "Failed to the delete the cron event '%s'", $hook ) );
				}
			}
		}

		if ( $deleted ) {
			$message = ( 1 == $deleted ) ? "Deleted the cron event '%2\$s'" : "Deleted %1\$d instances of the cron event '%2\$s'";
			WP_CLI::success( sprintf( $message, $deleted, $hook ) );
		} else {
			WP_CLI::error( sprintf( "Invalid cron event '%s'", $hook ) );
		}

	}

	/**
	 * Deletes a cron event.
	 *
	 * @param stdClass $event The event
	 * @return bool Whether the event was successfully deleted or not.
	 */
	protected static function delete_event( stdClass $event ) {
		$crons = _get_cron_array();

		if ( ! isset( $crons[$event->time][$event->hook][$event->sig] ) ) {
			return false;
		}

		wp_unschedule_event( $event->time, $event->hook, $event->args );
		return true;
	}

	/**
	 * Callback function to format a cron event.
	 *
	 * @param stdClass $event The event.
	 * @return stdClass The formatted event object.
	 */
	protected static function format_event( stdClass $event ) {

		$event->next_run          = get_date_from_gmt( date( 'Y-m-d H:i:s', $event->time ), self::$time_format );
		$event->next_run_gmt      = date( self::$time_format, $event->time );
		$event->next_run_relative = self::interval( $event->time - time() );
		$event->recurrence        = ( $event->schedule ) ? self::interval( $event->interval ) : 'Non-repeating';

		return $event;
	}

	/**
	 * Fetch an array of scheduled cron events.
	 *
	 * @return array|WP_Error An array of event objects, or a WP_Error object if there are no events scheduled.
	 */
	protected static function get_cron_events() {

		$crons  = _get_cron_array();
		$events = array();

		if ( empty( $crons ) ) {
			return new WP_Error(
				'no_events',
				'You currently have no scheduled cron events.'
			);
		}

		foreach ( $crons as $time => $hooks ) {
			foreach ( $hooks as $hook => $hook_events ) {
				foreach ( $hook_events as $sig => $data ) {

					$events["$hook-$sig"] = (object) array(
						'hook'     => $hook,
						'time'     => $time,
						'sig'      => $sig,
						'args'     => $data['args'],
						'schedule' => $data['schedule'],
						'interval' => isset( $data['interval'] ) ? $data['interval'] : null,
					);

				}
			}
		}

		$events = array_map( 'Cron_Event_Command::format_event', $events );

		return $events;

	}

	/**
	 * Convert a time interval into human-readable format.
	 *
	 * Similar to WordPress' built-in `human_time_diff()` but returns two time period chunks instead of just one.
	 *
	 * @param int $since An interval of time in seconds
	 * @return string The interval in human readable format
	 */
	private static function interval( $since ) {
		if ( $since <= 0 ) {
			return 'now';
		}

		$since = absint( $since );

		// array of time period chunks
		$chunks = array(
			array( 60 * 60 * 24 * 365 , \_n_noop( '%s year', '%s years' ) ),
			array( 60 * 60 * 24 * 30 , \_n_noop( '%s month', '%s months' ) ),
			array( 60 * 60 * 24 * 7, \_n_noop( '%s week', '%s weeks' ) ),
			array( 60 * 60 * 24 , \_n_noop( '%s day', '%s days' ) ),
			array( 60 * 60 , \_n_noop( '%s hour', '%s hours' ) ),
			array( 60 , \_n_noop( '%s minute', '%s minutes' ) ),
			array(  1 , \_n_noop( '%s second', '%s seconds' ) ),
		);

		// we only want to output two chunks of time here, eg:
		// x years, xx months
		// x days, xx hours
		// so there's only two bits of calculation below:

		// step one: the first chunk
		for ( $i = 0, $j = count( $chunks ); $i < $j; $i++ ) {
			$seconds = $chunks[$i][0];
			$name = $chunks[$i][1];

			// finding the biggest chunk (if the chunk fits, break)
			if ( ( $count = floor( $since / $seconds ) ) != 0 ){
				break;
			}
		}

		// set output var
		$output = sprintf( \_n( $name[0], $name[1], $count ), $count );

		// step two: the second chunk
		if ( $i + 1 < $j ) {
			$seconds2 = $chunks[$i + 1][0];
			$name2    = $chunks[$i + 1][1];

			if ( ( $count2 = floor( ( $since - ( $seconds * $count ) ) / $seconds2 ) ) != 0 ) {
				// add to output var
				$output .= ' ' . sprintf( \_n( $name2[0], $name2[1], $count2 ), $count2 );
			}
		}

		return $output;
	}

	private function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->fields, 'event' );
	}

}

/**
 * Manage WP-Cron schedules.
 */
class Cron_Schedule_Command extends WP_CLI_Command {

	private $fields = array(
		'name',
		'display',
		'interval',
	);

	/**
	 * List available cron schedules.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, json, csv, ids. Default: table.
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each cron schedule:
	 *
	 * * name
	 * * display
	 * * interval
	 *
	 * There are no additional fields.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cron schedule list
	 *
	 *     wp cron schedule list --fields=name --format=ids
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {
		$formatter = $this->get_formatter( $assoc_args );

		$schedules = self::get_schedules();

		if ( 'ids' == $formatter->format ) {
			echo implode( ' ', wp_list_pluck( $schedules, 'name' ) );
		} else {
			$formatter->display_items( $schedules );
		}

	}

	/**
	 * Callback function to format a cron schedule.
	 *
	 * @param array $schedule The schedule.
	 * @param string $name The schedule name.
	 * @return array The formatted schedule.
	 */
	protected static function format_schedule( array $schedule, $name ) {
		$schedule['name'] = $name;
		return $schedule;
	}

	/**
	* Return a list of the cron schedules sorted according to interval.
	*
	* @return array The array of cron schedules. Each schedule is itself an array.
	*/
	protected static function get_schedules() {
		$schedules = wp_get_schedules();
		if ( !empty( $schedules ) ) {
			uasort( $schedules, 'Cron_Schedule_Command::sort' );
			$schedules = array_map( 'Cron_Schedule_Command::format_schedule', $schedules, array_keys( $schedules ) );
		}
		return $schedules;
	}

	/**
	 * Callback function to sort the cron schedule array by interval.
	 *
	 */
	protected static function sort( array $a, array $b ) {
		return $a['interval'] - $b['interval'];
	}

	private function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->fields, 'schedule' );
	}

}

/**
 * Manage WP-Cron events and schedules.
 */
class Cron_Command extends WP_CLI_Command {

	/**
	 * Test the WP Cron spawning system and report back its status.
	 */
	public function test() {

		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			WP_CLI::error( 'The DISABLE_WP_CRON constant is set to true. WP-Cron spawning is disabled.' );
		}

		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			WP_CLI::warning( 'The ALTERNATE_WP_CRON constant is set to true. WP-Cron spawning is not asynchronous.' );
		}

		$spawn = self::get_cron_spawn();

		if ( is_wp_error( $spawn ) ) {
			WP_CLI::error( sprintf( 'WP-Cron spawn failed with error: %s', $spawn->get_error_message() ) );
		}

		$code    = wp_remote_retrieve_response_code( $spawn );
		$message = wp_remote_retrieve_response_message( $spawn );

		if ( 200 === $code ) {
			WP_CLI::success( 'WP-Cron spawning is working as expected.' );
		} else {
			WP_CLI::warning( sprintf( 'WP-Cron spawn succeeded but returned HTTP status code: %1$s %2$s', $code, $message ) );
		}

	}

	/**
	 * Spawn a request to `wp-cron.php` and return the response.
	 *
	 * This function is designed to mimic the functionality in `spawn_cron()` with the addition of returning
	 * the result of the `wp_remote_post()` request.
	 *
	 * @return WP_Error|array The response or WP_Error on failure.
	 */
	protected static function get_cron_spawn() {

		$doing_wp_cron = sprintf( '%.22F', microtime( true ) );

		$cron_request = apply_filters( 'cron_request', array(
			'url'  => site_url( 'wp-cron.php?doing_wp_cron=' . $doing_wp_cron ),
			'key'  => $doing_wp_cron,
			'args' => array(
				'timeout'   => 3,
				'blocking'  => true,
				'sslverify' => apply_filters( 'https_local_ssl_verify', true )
			)
		) );

		# Enforce a blocking request in case something that's hooked onto the 'cron_request' filter sets it to false
		$cron_request['args']['blocking'] = true;

		$result = wp_remote_post( $cron_request['url'], $cron_request['args'] );

		return $result;

	}

}

WP_CLI::add_command( 'cron',          'Cron_Command' );
WP_CLI::add_command( 'cron event',    'Cron_Event_Command' );
WP_CLI::add_command( 'cron schedule', 'Cron_Schedule_Command' );
