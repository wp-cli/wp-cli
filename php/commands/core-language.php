<?php

/**
 * Manage core language.
 *
 * ## EXAMPLES
 *
 *     # Install language
 *     $ wp core language install nl_NL
 *     Success: Language installed.
 *
 *     # Activate language
 *     $ wp core language activate nl_NL
 *     Success: Language activated.
 *
 *     # Uninstall language
 *     $ wp core language uninstall nl_NL
 *     Success: Language uninstalled.
 *
 *     # List installed languages
 *     $ wp core language list --status=installed
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 *     | language | english_name | native_name | status    | update    | updated             |
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 *     | nl_NL    | Dutch        | Nederlands  | installed | available | 2016-05-13 08:12:50 |
 *     +----------+--------------+-------------+-----------+-----------+---------------------+
 */
class Core_Language_Command extends WP_CLI\CommandWithTranslation {

	protected $obj_type = 'core';

}

WP_CLI::add_command( 'core language', 'Core_Language_Command', array(
	'before_invoke' => function() {
		if ( \WP_CLI\Utils\wp_version_compare( '4.0', '<' ) ) {
			WP_CLI::error( "Requires WordPress 4.0 or greater." );
		}
	})
);
