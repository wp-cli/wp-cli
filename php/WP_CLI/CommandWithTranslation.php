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
	 * List all languages available.
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
	 * @subcommand list
	 */
	public function list_( $args, $assoc_args ) {

		$translations = $this->get_all_languages();
		$available = $this->get_installed_languages();

		wp_clean_update_cache(); // Clear existing update caches.
		wp_version_check();      // Check for Core translation updates.
		wp_update_themes();      // Check for Theme translation updates.
		wp_update_plugins();     // Check for Plugin translation updates.
		$updates = wp_get_translation_updates(); // Retrieves a list of all translations updates available.

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
	 * <language>
	 * : Language code to install.
	 *
	 * [--activate]
	 * : If set, the language will be activated immediately after install.
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

		require_once ABSPATH . '/wp-admin/includes/translation-install.php';

		$response = wp_download_language_pack( $language_code );
		if ( $response == $language_code ) {
			\WP_CLI::success( "Language installed." );

			if ( \WP_CLI\Utils\get_flag_value( $assoc_args, 'activate' ) ) {
				$this->activate( array( $language_code ), array() );
			}
		} else {
			\WP_CLI::error( "Couldn't install language." );
		}

	}

	/**
	 * Updates the active translation of core, plugins, and themes.
	 *
	 * [--dry-run]
	 * : Preview which translations would be updated.
	 *
	 * @subcommand update
	 */
	public function update( $args, $assoc_args ) {

		// Ignore updates for the default locale.
		if ( 'en_US' == get_locale() ) {
			\WP_CLI::success( "Translations updates are not needed for the 'English (US)' locale." );

			return;
		}

		wp_clean_update_cache(); // Clear existing update caches.
		wp_version_check();      // Check for Core translation updates.
		wp_update_themes();      // Check for Theme translation updates.
		wp_update_plugins();     // Check for Plugin translation updates.

		$updates = wp_get_translation_updates(); // Retrieves a list of all translations updates available.

		if ( empty( $updates ) ) {
			\WP_CLI::success( 'Translations are up to date.' );

			return;
		}

		// Gets a list of all languages.
		$all_languages = $this->get_all_languages();

		// Formats the updates list.
		foreach ( $updates as $update ) {
			if ( 'plugin' == $update->type ) {
				$plugin_data = array_shift( get_plugins( '/' . $update->slug ) );
				$name		 = $plugin_data['Name'];
			} elseif ( 'theme' == $update->type ) {
				$theme_data	 = wp_get_theme( $update->slug );
				$name		 = $theme_data['Name'];
			} else { // Core
				$name = 'WordPress';
			}

			// Gets the translation data.
			$translation = (object) reset( wp_list_filter( $all_languages, array(
				'language' => $update->language
			) ) );

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

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

		$upgrader	 = new \Language_Pack_Upgrader( new \Automatic_Upgrader_Skin() );
		$results	 = array();

		// Update translations.
		foreach ( $updates as $update ) {
			\WP_CLI::line( "Updating '{$update->Language}' translation for {$update->Name} {$update->Version}..." );
			\WP_CLI::line( "Downloading translation from {$update->package}..." );

			$result = $upgrader->upgrade( $update );

			if ( $result ) {
				\WP_CLI::line( 'Translation updated successfully.' );
			} else {
				\WP_CLI::line( 'Translation update failed.' );
			}

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

		$response = translations_api( $this->obj_type );
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

}
