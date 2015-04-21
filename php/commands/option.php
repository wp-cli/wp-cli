<?php

/**
 * Manage options.
 *
 * ## OPTIONS
 *
 * [--format=json]
 * : Encode/decode values as JSON.
 *
 * ## EXAMPLES
 *
 *     wp option get siteurl
 *
 *     wp option add my_option foobar
 *
 *     wp option update my_option '{"foo": "bar"}' --format=json
 *
 *     wp option delete my_option
 */
class Option_Command extends WP_CLI_Command {

	/**
	 * Get an option.
	 *
	 * @synopsis <key> [--format=<format>]
	 */
	public function get( $args, $assoc_args ) {
		list( $key ) = $args;

		$value = get_option( $key );

		if ( false === $value )
			die(1);

		WP_CLI::print_value( $value, $assoc_args );
	}

	/**
	 * Add an option.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : The name of the option to add.
	 *
	 * [<value>]
	 * : The value of the option to add. If ommited, the value is read from STDIN.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value. Default is plaintext.
	 *
	 * [--autoload=<autoload>]
	 * : Should this option be automatically loaded. Accepted values: yes, no. Default: yes
	 *
	 * ## EXAMPLES
	 *
	 *     # Create an option by reading a JSON file
	 *     wp option add my_option --format=json < config.json
	 */
	public function add( $args, $assoc_args ) {
		$key = $args[0];

		$value = WP_CLI::get_value_from_arg_or_stdin( $args, 1 );
		$value = WP_CLI::read_value( $value, $assoc_args );

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'autoload' ) === 'no' ) {
			$autoload = 'no';
		} else {
			$autoload = 'yes';
		}

		if ( !add_option( $key, $value, '', $autoload ) ) {
			WP_CLI::error( "Could not add option '$key'. Does it already exist?" );
		} else {
			WP_CLI::success( "Added '$key' option." );
		}
	}

	/**
	 * List options.
	 *
	 * [--search=<pattern>]
	 * : Use wildcards ( * and ? ) to match option name.
	 *
	 * [--autoload=<value>]
	 * : Match only autoload options when value is on, and only not-autoload option when off.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific object fields.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value.
	 * : total_bytes displays the total size of matching options in bytes.
	 * : Accepted values: table, json, csv, count, total_bytes. Default: table
	 *
	 * ## EXAMPLES
	 *
	 *     # Get the total size of all autoload options
	 *     wp option list --autoload=on --format=total_bytes
	 *
	 *     # Find biggest transients
	 *     wp option list --search="*_transient_*" --fields=option_name,size_bytes | sort -n -k 2 | tail
	 *
	 *     # List all options begining with "i2f_"
	 *     wp option list --search "i2f_*"
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * This field will be displayed by default for each matching option:
	 *
	 * * option_name
	 * * option_value
	 *
	 * These fields are optionally available:
	 *
	 * * autoload
	 * * size_bytes
	 *
	 * @subcommand list
	 * @synopsis [--search=<glob-style-pattern>] [--autoload=<value>] [--fields=<fields>] [--format=<format>]
	 */
	public function list_( $args, $assoc_args ) {

		global $wpdb;
		$pattern = '%';
		$fields = array( 'option_name', 'option_value' );
		$size_query = ",LENGTH(option_value) AS `size_bytes`";
		$autoload_query = '';

		if ( isset( $assoc_args['search'] ) ) {
			$pattern = self::esc_like( esc_sql( $assoc_args['search'] ) );
			// substitute wildcards
			$pattern = str_replace( '*', '%', $pattern );
			$pattern = str_replace( '?', '_', $pattern );
		}

		if ( isset( $assoc_args['fields'] ) ) {
			$fields = explode( ',', $assoc_args['fields'] );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' ) === 'total_bytes' ) {
			$fields = array( 'size_bytes' );
			$size_query = ",SUM(LENGTH(option_value)) AS `size_bytes`";
		}

		if ( isset( $assoc_args['autoload'] ) ) {
			if ( 'on' === $assoc_args['autoload'] ) {
				$autoload_query = " AND autoload='yes'";
			} elseif ( 'off' === $assoc_args['autoload'] ) {
				$autoload_query = " AND autoload='no'";
			} else {
				WP_CLI::error( "Value of '--autoload' should be on or off." );
			}
		}

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT `option_name`,`option_value`,`autoload`" . $size_query
					. " FROM `$wpdb->options` WHERE `option_name` LIKE %s" . $autoload_query,
				$pattern
			)
		);

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'format' ) === 'total_bytes' ) {
			WP_CLI::line( $results[0]->size_bytes );
		} else {
			$formatter = new \WP_CLI\Formatter(
				$assoc_args,
				$fields
			);
			$formatter->display_items( $results );
		}
	}

	/**
	 * Update an option.
	 *
	 * ## OPTIONS
	 *
	 * <key>
	 * : The name of the option to add.
	 *
	 * [<value>]
	 * : The new value. If ommited, the value is read from STDIN.
	 *
	 * [--format=<format>]
	 * : The serialization format for the value. Default is plaintext.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update an option by reading from a file
	 *     wp option update my_option < value.txt
	 *
	 *     # Update one option on multiple sites using xargs
	 *     wp site list --field=url | xargs -n1 -I {} sh -c 'wp --url={} option update <key> <value>'
	 *
	 * @alias set
	 */
	public function update( $args, $assoc_args ) {
		$key = $args[0];

		$value = WP_CLI::get_value_from_arg_or_stdin( $args, 1 );
		$value = WP_CLI::read_value( $value, $assoc_args );

		$value = sanitize_option( $key, $value );
		$old_value = sanitize_option( $key, get_option( $key ) );

		if ( $value === $old_value ) {
			WP_CLI::success( "Value passed for '$key' option is unchanged." );
		} else {
			if ( update_option( $key, $value ) ) {
				WP_CLI::success( "Updated '$key' option." );
			} else {
				WP_CLI::error( "Could not update option '$key'." );
			}
		}
	}

	/**
	 * Delete an option.
	 *
	 * @synopsis <key>
	 */
	public function delete( $args ) {
		list( $key ) = $args;

		if ( !delete_option( $key ) ) {
			WP_CLI::error( "Could not delete '$key' option. Does it exist?" );
		} else {
			WP_CLI::success( "Deleted '$key' option." );
		}
	}

	private static function esc_like( $old ) {
		global $wpdb;

		// Remove notices in 4.0 and support backwards compatibility
		if( method_exists( $wpdb, 'esc_like' ) ) {
			// 4.0
			$old = $wpdb->esc_like( $old );
		} else {
			// 3.9 or less
			$old = like_escape( esc_sql( $old ) );
		}

		return $old;
	}
}

WP_CLI::add_command( 'option', 'Option_Command' );
