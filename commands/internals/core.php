<?php

// Add the command to the wp-cli
WP_CLI::addCommand('core', 'CoreCommand');

/**
 * Implement core command
 *
 * @package wp-cli
 * @subpackage commands/internals
 */
class CoreCommand extends WP_CLI_Command {

	/**
	 * Display the WordPress version.
	 */
	public function version( $args = array(), $assoc_args = array() ) {
		global $wp_version, $wp_db_version, $tinymce_version, $manifest_version;

		$color = '%G';
		$version_text = $wp_version;
		$version_types = array(
			'-RC' => array( 'release candidate', '%y' ),
			'-beta' => array( 'beta', '%B' ),
			'-' => array( 'in development', '%R' ),
		);
		foreach( $version_types as $needle => $type ) {
			if ( stristr( $wp_version, $needle ) ) {
				list( $version_text, $color ) = $type;
				$version_text = "$color$wp_version%n (stability: $version_text)";
				break;
			}
		}
		WP_CLI::line( "WordPress version:\t$version_text" );

		if ( isset( $assoc_args['extra'] ) ) {
			WP_CLI::line();
			WP_CLI::line( "Database revision:\t$wp_db_version" );

			preg_match( '/(\d)(\d+)-/', $tinymce_version, $match );
			$human_readable_tiny_mce = $match? $match[1] . '.' . $match[2] : '';
			WP_CLI::line( "TinyMCE version:\t"  . ( $human_readable_tiny_mce? "$human_readable_tiny_mce ($tinymce_version)" : $tinymce_version ) );

			WP_CLI::line( "Manifest revision:\t$manifest_version" );
		}
	}

	/**
	 * Update the WordPress core
	 *
	 * @param array $args
	 * @return void
	 */
	function update($args) {
		WP_CLI::line('Updating the WordPress core.');

		if(!class_exists('Core_Upgrader')) {
			require_once(ABSPATH.'wp-admin/includes/class-wp-upgrader.php');
		}
		ob_start();
		$upgrader = new Core_Upgrader(new CLI_Upgrader_Skin);
		$result = $upgrader->upgrade($current);
		$feedback = ob_get_clean();

		// Borrowed verbatim from wp-admin/update-core.php
		if(is_wp_error($result) ) {
			if('up_to_date' != $result->get_error_code()) {
				WP_CLI::error('Installation failed ('.WP_CLI::errorToString($result).').');
			}
			else {
				WP_CLI::success(WP_CLI::errorToString($result));
			}
		}
		else {
			WP_CLI::success('WordPress upgraded successfully.');
		}
	}

	/**
	 * Help function for this command
	 */
	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp core update
   or: wp core version [--extra]
EOB
		);
	}
}
