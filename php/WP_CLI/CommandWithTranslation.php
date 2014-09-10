<?php

namespace WP_CLI;

/**
 * Base class for WP-CLI commands that deal with translations
 *
 * @package wp-cli
 */
abstract class CommandWithTranslation extends \WP_CLI_Command {

	protected $obj_type;

	protected $obj_fields = array(
		'language',
		'english_name',
		'native_name',
		'status',
		'updated',
		);

	/**
	 * List all languages available.
	 *
	 * [--keys=<keys>]
	 * : Limit output to metadata of specific keys.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json, count. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each translation:
	 *
	 * * language
	 * * english_name
	 * * native_name
	 * * status
	 * * updated
	 *
	 * These fields are optionally available:
	 *
	 * * version
	 * * package
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		require_once ABSPATH . '/wp-admin/includes/translation-install.php';

		$response = translations_api( $this->obj_type );
		$translations = ! empty( $response['translations'] ) ? $response['translations'] : array();
		$available = wp_get_installed_translations( $this->obj_type );
		$available = ! empty( $available['default'] ) ? array_keys( $available['default'] ) : array();
		$translations = array_map( function( $translation ) use ( $available ) {
			$translation['status'] = ( in_array( $translation['language'], $available ) ) ? 'installed' : 'uninstalled';
			return $translation;
		}, $translations );

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $translations );

	}

	/**
	 * Install a given language.
	 *
	 * <language>
	 * : Language code to install.
	 *
	 * @subcommand install
	 */
	public function install( $args, $assoc_args ) {

		list( $language_code ) = $args;

		$available = wp_get_installed_translations( $this->obj_type );
		$available = ! empty( $available['default'] ) ? array_keys( $available['default'] ) : array();
		if ( in_array( $language_code, $available ) ) {
			\WP_CLI::warning( "Language already installed." );
			exit;
		}

		require_once ABSPATH . '/wp-admin/includes/translation-install.php';

		$response = wp_download_language_pack( $language_code );
		if ( $response == $language_code ) {
			\WP_CLI::success( "Language installed." );
		} else {
			\WP_CLI::error( "Couldn't install language." );
		}

	}

	/**
	 * Get Formatter object based on supplied parameters.
	 *
	 * @param array $assoc_args Parameters passed to command. Determines formatting.
	 * @return \WP_CLI\Formatter
	 */
	protected function get_formatter( &$assoc_args ) {
		return new \WP_CLI\Formatter( $assoc_args, $this->obj_fields, $this->obj_type );
	}

}
