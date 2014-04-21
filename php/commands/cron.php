<?php

/**
 * Manage WP-Cron events.
 *
 * @package wp-cli
 */
class Cron_Event_Command extends WP_CLI_Command {

	/**
	 * List scheduled cron events.
	 *
	 * @subcommand list
	 * @synopsis [--format=<format>]
	 */
	public function _list( $args, $assoc_args ) {

		$values = array_merge( array(
			'format' => 'table',
		), $assoc_args );
		$events = self::get_cron_events();

		if ( is_wp_error( $events ) ) {
			WP_CLI::line( WP_CLI::error_to_string( $events ) );
			exit;
		}

		$fields = array(
			'hook',
			'next_run',
			'next_run_gmt',
			'next_run_relative',
			'recurrence',
		);

		\WP_CLI\Utils\format_items( $values['format'], $events, $fields );

	}

	/**
	 * Run the next scheduled cron event for the given hook.
	 *
	 * ## OPTIONS
	 *
	 * <hook>
	 * : The hook name
	 *
	 * @synopsis <hook>
	 */
	public function run( $args, $assoc_args ) {

		$hook   = $args[0];
		$result = false;
		$events = self::get_cron_events();

		if ( is_wp_error( $events ) ) {
			WP_CLI::error( $events );
		}

		foreach ( $events as $id => $event ) {
			if ( $event->hook == $hook ) {
				$result = self::run_event( $event );
				break;
			}
		}

		if ( $result ) {
			WP_CLI::success( sprintf( "Successfully executed the cron event '%s'", $hook ) );
		} else {
			WP_CLI::error( sprintf( "Failed to the execute the cron event '%s'", $hook ) );
		}

	}

	/**
	 * Executes an event immediately by scheduling a new single event with the same arguments.
	 *
	 * @param stdClass $event The event
	 * @return bool Whether the event was successfully executed or not.
	 */
	protected static function run_event( stdClass $event ) {

		delete_transient( 'doing_cron' );
		$scheduled = wp_schedule_single_event( time()-1, $event->hook, $event->args );

		if ( false === $scheduled ) {
			return false;
		}

		spawn_cron();

		return true;

	}

	/**
	 * Delete the next scheduled cron event for the given hook.
	 *
	 * ## OPTIONS
	 *
	 * <hook>
	 * : The hook name
	 *
	 * @synopsis <hook>
	 */
	public function delete( $args, $assoc_args ) {

		$hook   = $args[0];
		$result = false;
		$events = self::get_cron_events();

		if ( is_wp_error( $events ) ) {
			WP_CLI::error( $events );
		}

		foreach ( $events as $id => $event ) {
			if ( $event->hook == $hook ) {
				$result = self::delete_event( $event );
				break;
			}
		}

		if ( $result ) {
			WP_CLI::success( sprintf( "Successfully deleted the cron event '%s'", $hook ) );
		} else {
			WP_CLI::error( sprintf( "Failed to the delete the cron event '%s'", $hook ) );
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
		$time_format = 'Y-m-d H:i:s';

		$event->next_run          = get_date_from_gmt( date( 'Y-m-d H:i:s', $event->time ), $time_format );
		$event->next_run_gmt      = date( $time_format, $event->time );
		$event->next_run_relative = \WP_CLI\Utils\time_since( time(), $event->time );
		$event->recurrence        = ( $event->schedule ) ? \WP_CLI\Utils\interval( $event->interval ) : 'Non-repeating';

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

		// @TODO rename these vars a bit more better nicely nicer:
		foreach ( $crons as $time => $cron ) {
			foreach ( $cron as $hook => $dings ) {
				foreach ( $dings as $sig => $data ) {

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

}

/**
 * Manage WP-Cron schedules.
 */
class Cron_Schedule_Command extends WP_CLI_Command {

	/**
	 * List available cron schedules.
	 *
	 * @subcommand list
	 * @synopsis [--format=<format>]
	 */
	public function _list( $args, $assoc_args ) {

		$values = array_merge( array(
			'format' => 'table',
		), $assoc_args );

		$schedules = self::get_schedules();

		$fields = array(
			'name',
			'display',
			'interval',
		);

		\WP_CLI\Utils\format_items( $values['format'], $schedules, $fields );

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

}

/**
 * Manage WP-Cron events and schedules.
 */
class Cron_Command extends WP_CLI_Command {

	/**
	 * Test the WP Cron spawning system and report back any errors.
	 */
	public function test() {

		$status = self::test_cron_spawn();

		if ( is_wp_error( $status ) ) {
			WP_CLI::error( $status );
		} else {
			WP_CLI::success( 'WP-Cron is working as expected.' );
		}

	}

	/**
	 * Gets the status of WP-Cron functionality on the site by performing a test spawn.
	 *
	 * This function is designed to mimic the functionality in `spawn_cron()` with the addition of checking
	 * the return value of the call to `wp_remote_post()`.
	 *
	 * @return bool|WP_Error Boolean true if the cron spawn test is successful, WP_Error object if not.
	 */
	protected static function test_cron_spawn() {

		if ( defined( 'ALTERNATE_WP_CRON' ) && ALTERNATE_WP_CRON ) {
			return true;
		}

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

		if ( is_wp_error( $result ) ) {
			return $result;
		} else {
			return true;
		}

	}

}

WP_CLI::add_command( 'cron',          'Cron_Command' );
WP_CLI::add_command( 'cron event',    'Cron_Event_Command' );
WP_CLI::add_command( 'cron schedule', 'Cron_Schedule_Command' );
