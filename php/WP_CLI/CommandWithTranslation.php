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
		'update',
		'updated',
		);

	/**
	 * List all available languages.
	 *
	 * [--field=<field>]
	 * : Display the value of a single field
	 *
	 * [--<field>=<value>]
	 * : Filter results by key=value pairs.
	 *
	 * [--fields=<fields>]
	 * : Limit the output to specific fields.
	 *
	 * [--format=<format>]
	 * : Accepted values: table, csv, json. Default: table
	 *
	 * ## AVAILABLE FIELDS
	 *
	 * These fields will be displayed by default for each translation:
	 *
	 * * language
	 * * english_name
	 * * native_name
	 * * status
	 * * update
	 * * updated
	 *
	 * These fields are optionally available:
	 *
	 * * version
	 * * package
	 *
	 * ## EXAMPLES
	 *
	 *     # List language,english_name,status fields of available languages.
	 *     $ wp core language list --fields=language,english_name,status
	 *     +----------------+-------------------------+-------------+
	 *     | language       | english_name            | status      |
	 *     +----------------+-------------------------+-------------+
	 *     | ar             | Arabic                  | uninstalled |
	 *     | ary            | Moroccan Arabic         | uninstalled |
	 *     | az             | Azerbaijani             | uninstalled |
	 *
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		$translations = $this->get_all_languages();
		$available = $this->get_installed_languages();

		$updates = $this->get_translation_updates();

		$current_locale = get_locale();
		$translations = array_map( function( $translation ) use ( $available, $current_locale, $updates ) {
			$translation['status'] = ( in_array( $translation['language'], $available ) ) ? 'installed' : 'uninstalled';
			if ( $current_locale == $translation['language'] ) {
				$translation['status'] = 'active';
			}

			$update = wp_list_filter( $updates, array(
				'language' => $translation['language']
			) );
			if ( $update ) {
				$translation['update'] = 'available';
			} else {
				$translation['update'] = 'none';
			}

			return $translation;
		}, $translations );

		foreach( $translations as $key => $translation ) {

			$fields = array_keys( $translation );
			foreach( $fields as $field ) {
				if ( isset( $assoc_args[ $field ] ) && $assoc_args[ $field ] != $translation[ $field ] ) {
					unset( $translations[ $key ] );
				}
			}
		}

		$formatter = $this->get_formatter( $assoc_args );
		$formatter->display_items( $translations );

	}

	/**
	 * Callback to sort array by a 'language' key.
	 */
	protected function sort_translations_callback( $a, $b ) {
		return strnatcasecmp( $a['language'], $b['language'] );
	}

	/**
	 * Install a given language.
	 *
	 * Downloads the language pack from WordPress.org.
	 *
	 * <language>
	 * : Language code to install.
	 *
	 * [--activate]
	 * : If set, the language will be activated immediately after install.
	 *
	 * ## EXAMPLES
	 *
	 *     # Install the Japanese language.
	 *     $ wp core language install ja
	 *     Success: Language installed.
	 *
	 * @subcommand install
	 */
	public function install( $args, $assoc_args ) {

		list( $language_code ) = $args;

		$available = $this->get_installed_languages();

		if ( in_array( $language_code, $available ) ) {
			\WP_CLI::warning( "Language already installed." );
			exit;
		}

		$response = $this->download_language_pack( $language_code );
		if ( ! is_wp_error( $response ) ) {
			\WP_CLI::success( "Language installed." );

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'activate' ) ) {
				$this->activate( array( $language_code ), array() );
			}
		} else {
			\WP_CLI::error( $response );
		}

	}

	/**
	 * Update installed languages.
	 *
	 * Updates installed languages for core, plugins and themes.
	 *
	 * [--dry-run]
	 * : Preview which translations would be updated.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp core language update
	 *     Updating 'Japanese' translation for Akismet 3.1.11...
	 *     Downloading translation from https://downloads.wordpress.org/translation/plugin/akismet/3.1.11/ja.zip...
	 *     Translation updated successfully.
	 *     Updating 'Japanese' translation for Twenty Fifteen 1.5...
	 *     Downloading translation from https://downloads.wordpress.org/translation/theme/twentyfifteen/1.5/ja.zip...
	 *     Translation updated successfully.
	 *     Success: Updated 2/2 translations.
	 *
	 * @subcommand update
	 */
	public function update( $args, $assoc_args ) {

		$updates = $this->get_translation_updates();
		if ( empty( $updates ) ) {
			\WP_CLI::success( 'Translations are up to date.' );

			return;
		}

		// Gets a list of all languages.
		$all_languages = $this->get_all_languages();

		// Formats the updates list.
		foreach ( $updates as $update ) {
			if ( 'plugin' == $update->type ) {
				$plugins	 = get_plugins( '/' . $update->slug );
				$plugin_data = array_shift( $plugins );
				$name		 = $plugin_data['Name'];
			} elseif ( 'theme' == $update->type ) {
				$theme_data	 = wp_get_theme( $update->slug );
				$name		 = $theme_data['Name'];
			} else { // Core
				$name = 'WordPress';
			}

			// Gets the translation data.
			$translation = wp_list_filter( $all_languages, array(
				'language' => $update->language
			) );
			$translation = (object) reset( $translation );

			$update->Type		 = ucfirst( $update->type );
			$update->Name		 = $name;
			$update->Version	 = $update->version;
			$update->Language	 = $translation->english_name;
		}

		// Only preview which translations would be updated.
		if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'dry-run' ) ) {
			\WP_CLI::line( sprintf( 'Available %d translations updates:', count( $updates ) ) );
			\WP_CLI\Utils\format_items( 'table', $updates, array( 'Type', 'Name', 'Version', 'Language' ) );

			return;
		}

		$upgrader = 'WP_CLI\\LanguagePackUpgrader';
		$results = array();

		// Update translations.
		foreach ( $updates as $update ) {
			\WP_CLI::line( "Updating '{$update->Language}' translation for {$update->Name} {$update->Version}..." );

			$result = Utils\get_upgrader( $upgrader )->upgrade( $update );

			$results[] = $result;
		}

		$num_to_update	 = count( $updates );
		$num_updated	 = count( array_filter( $results ) );

		$line = "Updated $num_updated/$num_to_update translations.";

		if ( $num_to_update == $num_updated ) {
			\WP_CLI::success( $line );
		} else if ( $num_updated > 0 ) {
			\WP_CLI::warning( $line );
		} else {
			\WP_CLI::error( $line );
		}

	}

	/**
	 * Activate a given language.
	 *
	 * <language>
	 * : Language code to activate.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp core language activate ja
	 *     Success: Language activated.
	 *
	 * @subcommand activate
	 */
	public function activate( $args, $assoc_args ) {

		list( $language_code ) = $args;

		$available = $this->get_installed_languages();

		if ( ! in_array( $language_code, $available ) ) {
			\WP_CLI::error( "Language not installed." );
		}

		if ( $language_code == 'en_US' ) {
			$language_code = '';
		}

		update_option( 'WPLANG', $language_code );
		\WP_CLI::success( "Language activated." );
	}

	/**
	 * Get all updates available for all translations
	 *
	 * @return array
	 */
	private function get_translation_updates() {
		$available = $this->get_installed_languages();
		$func = function() use ( $available ) {
			return $available;
		};
		$filters = array( 'plugins_update_check_locales', 'themes_update_check_locales' );
		foreach( $filters as $filter ) {
			add_filter( $filter, $func );
		}
		$this->wp_clean_update_cache(); // Clear existing update caches.
		wp_version_check();      // Check for Core translation updates.
		wp_update_themes();      // Check for Theme translation updates.
		wp_update_plugins();     // Check for Plugin translation updates.
		foreach( $filters as $filter ) {
			remove_filter( $filter, $func );
		}
		$updates = wp_get_translation_updates(); // Retrieves a list of all translations updates available.
		return $updates;
	}

	/**
	 * Download a language pack.
	 *
	 * @see wp_download_language_pack()
	 *
	 * @param string $download Language code to download.
	 * @return string|WP_Error Returns the language code if successfully downloaded, or a WP_Error object on failure.
	 */
	private function download_language_pack( $download ) {

		$translations = $this->get_all_languages();

		foreach ( $translations as $translation ) {
			if ( $translation['language'] === $download ) {
				$translation_to_load = true;
				break;
			}
		}

		if ( empty( $translation_to_load ) ) {
			return new \WP_Error( 'not_found', "Language '{$download}' not found." );
		}
		$translation = (object) $translation;

		$translation->type = 'core';

		$upgrader = 'WP_CLI\\LanguagePackUpgrader';
		$result = Utils\get_upgrader( $upgrader )->upgrade( $translation, array( 'clear_update_cache' => false ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		} else if ( ! $result ) {
			return new \WP_Error( 'not_installed', "Could not install language '{$download}'." );
		}

		return $translation->language;
	}

	/**
	 * Return a list of installed languages.
	 *
	 * @return array
	 */
	protected function get_installed_languages() {
		$available = wp_get_installed_translations( $this->obj_type );
		$available = ! empty( $available['default'] ) ? array_keys( $available['default'] ) : array();
		$available[] = 'en_US';

		return $available;
	}

	/**
	 * Return a list of all languages
	 *
	 * @return array
	 */
	protected function get_all_languages() {
		require_once ABSPATH . '/wp-admin/includes/translation-install.php';
		require ABSPATH . WPINC . '/version.php';

		$response = translations_api( $this->obj_type, array( 'version' => $wp_version ) );
		if ( is_wp_error( $response ) ) {
			\WP_CLI::error( $response );
		}
		$translations = ! empty( $response['translations'] ) ? $response['translations'] : array();

		$en_us = array(
			'language' => 'en_US',
			'english_name' => 'English (United States)',
			'native_name' => 'English (United States)',
			'updated' => '',
		);

		array_push( $translations, $en_us );
		uasort( $translations, array( $this, 'sort_translations_callback' ) );

		return $translations;
	}

	/**
	 * Uninstall a given language.
	 *
	 * <language>
	 * : Language code to uninstall.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp core language uninstall ja
	 *     Success: Language uninstalled.
	 *
	 * @subcommand uninstall
	 */
	public function uninstall( $args, $assoc_args ) {
		global $wp_filesystem;

		list( $language_code ) = $args;

		$available = $this->get_installed_languages();

		if ( ! in_array( $language_code, $available ) ) {
			\WP_CLI::error( "Language not installed." );
		}

		$dir = 'core' === $this->obj_type ? '' : "/$this->obj_type";
		$files = scandir( WP_LANG_DIR . $dir );
		if ( ! $files ) {
			\WP_CLI::error( "No files found in language directory." );
		}

		$current_locale = get_locale();
		if ( $language_code === $current_locale ) {
			\WP_CLI::warning( "The '{$language_code}' language is active." );
			exit;
		}

		// As of WP 4.0, no API for deleting a language pack
		WP_Filesystem();
		$deleted = false;
		foreach ( $files as $file ) {
			if ( '.' === $file[0] || is_dir( $file ) ) {
				continue;
			}
			$extension_length = strlen( $language_code ) + 4;
			$ending = substr( $file, -$extension_length );
			if ( ! in_array( $file, array( $language_code . '.po', $language_code . '.mo' ) ) && ! in_array( $ending, array( '-' . $language_code . '.po', '-' . $language_code . '.mo' ) ) ) {
				continue;
			}
			$deleted = $wp_filesystem->delete( WP_LANG_DIR . $dir . '/' . $file );
		}

		if ( $deleted ) {
			\WP_CLI::success( "Language uninstalled." );
		} else {
			\WP_CLI::error( "Couldn't uninstall language." );
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

	/**
	 * Replicates wp_clean_update_cache() for use in WP 4.0
	 */
	private static function wp_clean_update_cache() {
		if ( function_exists( 'wp_clean_plugins_cache' ) ) {
			wp_clean_plugins_cache();
		} else {
			delete_site_transient( 'update_plugins' );
		}
		wp_clean_themes_cache();
		delete_site_transient( 'update_core' );
	}

}
