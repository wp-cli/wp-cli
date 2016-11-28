<?php

WP_CLI::addCommand('version', 'VersionCommand');

/**
 * Implement version command
 *
 * @package wp-cli
 * @subpackage commands/internals
 * @author Nikolay Bachiyski <nb@nikolay.bg>
 */
class VersionCommand extends WP_CLI_Command {

	var $default_subcommand = 'core';

	public function core( $args = array() ) {
		global $wp_version;
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
	}

	public function extra( $args = array() ) {
		global $wp_version, $wp_db_version, $tinymce_version, $manifest_version;
		$this->core( $args );
		WP_CLI::line();
		WP_CLI::line( "Database revision:\t$wp_db_version" );

		preg_match( '/(\d)(\d+)-/', $tinymce_version, $match );
		$human_readable_tiny_mce = $match? $match[1] . '.' . $match[2] : '';
		WP_CLI::line( "TinyMCE version:\t"  . ( $human_readable_tiny_mce? "$human_readable_tiny_mce ($tinymce_version)" : $tinymce_version ) );

		WP_CLI::line( "Manifest revision:\t$manifest_version" );
	}

	public static function help() {
		WP_CLI::line( <<<EOB
usage: wp version <sub-command>

Available sub-commands:
   core       WordPress core version
   extra      Detailed version information
EOB
		);
	}
}