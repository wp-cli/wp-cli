<?php

/**
 * Manage WP-Cron schedules.
 *
 * ## EXAMPLES
 *
 *     # List available cron schedules
 *     $ wp cron schedule list
 *     +------------+-------------+----------+
 *     | name       | display     | interval |
 *     +------------+-------------+----------+
 *     | hourly     | Once Hourly | 3600     |
 *     | twicedaily | Twice Daily | 43200    |
 *     | daily      | Once Daily  | 86400    |
 *     +------------+-------------+----------+
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
	 * [--field=<field>]
	 * : Prints the value of a single field for each schedule.
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - ids
	 *   - json
	 *   - yaml
	 * ---
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
	 *     # List available cron schedules
	 *     $ wp cron schedule list
	 *     +------------+-------------+----------+
	 *     | name       | display     | interval |
	 *     +------------+-------------+----------+
	 *     | hourly     | Once Hourly | 3600     |
	 *     | twicedaily | Twice Daily | 43200    |
	 *     | daily      | Once Daily  | 86400    |
	 *     +------------+-------------+----------+
	 *
	 *     # List id of available cron schedule
	 *     $ wp cron schedule list --fields=name --format=ids
	 *     hourly twicedaily daily
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

WP_CLI::add_command( 'cron schedule', 'Cron_Schedule_Command' );
