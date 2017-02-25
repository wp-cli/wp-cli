<?php

/**
 * Manage theme mods.
 *
 * ## EXAMPLES
 *
 *     # Set the 'background_color' theme mod to '000000'.
 *     $ wp theme mod set background_color 000000
 *     Success: Theme mod background_color set to 000000
 *
 *     # Get single theme mod in JSON format.
 *     $ wp theme mod get background_color --format=json
 *     [{"key":"background_color","value":"dd3333"}]
 *
 *     # Remove all theme mods.
 *     $ wp theme mod remove --all
 *     Success: Theme mods removed.
 */
class Theme_Mod_command extends WP_CLI_Command {

	private $fields = array(
		'key',
		'value'
	);

	/**
	 * Get one or more theme mods.
	 *
	 * ## OPTIONS
	 *
	 * [<mod>...]
	 * : One or more mods to get.
	 *
	 * [--field=<field>]
	 * : Returns the value of a single field.
	 *
	 * [--all]
	 * : List all theme mods
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - csv
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # Get all theme mods.
	 *     $ wp theme mod get --all
	 *     +------------------+---------+
	 *     | key              | value   |
	 *     +------------------+---------+
	 *     | background_color | dd3333  |
	 *     | link_color       | #dd9933 |
	 *     | main_text_color  | #8224e3 |
	 *     +------------------+---------+
	 *
	 *     # Get single theme mod in JSON format.
	 *     $ wp theme mod get background_color --format=json
	 *     [{"key":"background_color","value":"dd3333"}]
	 *
	 *     # Get value of a single theme mod.
	 *     $ wp theme mod get background_color --field=value
	 *     dd3333
	 *
	 *     # Get multiple theme mods.
	 *     $ wp theme mod get background_color header_textcolor
	 *     +------------------+--------+
	 *     | key              | value  |
	 *     +------------------+--------+
	 *     | background_color | dd3333 |
	 *     | header_textcolor |        |
	 *     +------------------+--------+
	 */
	public function get( $args = array(), $assoc_args = array() ) {

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) && empty( $args ) ) {
			WP_CLI::error( "You must specify at least one mod or use --all." );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) ) {
			$args = array();
		}

		$list = array();
		$mods = get_theme_mods();
		if ( ! is_array( $mods ) ) {
			// if no mods are set (perhaps new theme), make sure foreach still works
			$mods = array();
		}
		foreach ( $mods as $k => $v ) {
			// if mods were given, skip the others
			if ( ! empty( $args ) && ! in_array( $k, $args ) ) continue;

			if ( is_array( $v ) ) {
				$list[] = array( 'key' => $k, 'value' => '=>' );
				foreach ( $v as $_k => $_v ) {
					$list[] = array( 'key' => "    $_k", 'value' => $_v );
				}
			} else {
				$list[] = array( 'key' => $k, 'value' => $v );
			}

		}

		// For unset mods, show blank value
		foreach ( $args as $mod ) {
			if ( ! isset( $mods[ $mod ] ) ) {
				$list[] = array( 'key' => $mod, 'value' => '' );
			}
		}

		$formatter = new \WP_CLI\Formatter( $assoc_args, $this->fields, 'thememods' );
		$formatter->display_items( $list );

	}

	/**
	 * Remove one or more theme mods.
	 *
	 * ## OPTIONS
	 *
	 * [<mod>...]
	 * : One or more mods to remove.
	 *
	 * [--all]
	 * : Remove all theme mods.
	 *
	 * ## EXAMPLES
	 *
	 *     # Remove all theme mods.
	 *     $ wp theme mod remove --all
	 *     Success: Theme mods removed.
	 *
	 *     # Remove single theme mod.
	 *     $ wp theme mod remove background_color
	 *     Success: 1 mod removed.
	 *
	 *     # Remove multiple theme mods.
	 *     $ wp theme mod remove background_color header_textcolor
	 *     Success: 2 mods removed.
	 */
	public function remove( $args = array(), $assoc_args = array() ) {

		if ( ! \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) && empty( $args ) ) {
			WP_CLI::error( "You must specify at least one mod or use --all." );
		}

		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'all' ) ) {
			remove_theme_mods();
			WP_CLI::success( 'Theme mods removed.' );
			return;
		}

		foreach ( $args as $mod ) {
			remove_theme_mod( $mod );
		}

		$count = count( $args );
		$success_message = ( 1 === $count ) ? '%d mod removed.' : '%d mods removed.';
		WP_CLI::success( sprintf( $success_message, $count ) );

	}

	/**
	 * Set the value of a theme mod.
	 *
	 * ## OPTIONS
	 *
	 * <mod>
	 * : The name of the theme mod to set or update.
	 *
	 * <value>
	 * : The new value.
	 *
	 * ## EXAMPLES
	 *
	 *     # Set theme mod
	 *     $ wp theme mod set background_color 000000
	 *     Success: Theme mod background_color set to 000000
	 */
	public function set( $args = array(), $assoc_args = array() ) {
		list( $mod, $value ) = $args;

		set_theme_mod( $mod, $value );

		if ( $value == get_theme_mod( $mod ) ) {
			WP_CLI::success( sprintf( "Theme mod %s set to %s.", $mod, $value ) );
		} else {
			WP_CLI::success( sprintf( "Could not update theme mod %s.", $mod ) );
		}
	}

}

WP_CLI::add_command( 'theme mod', 'Theme_Mod_Command' );
