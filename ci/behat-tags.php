<?php
/**
 * Generate a list of tags to skip during the test run.
 *
 * Require a minimum version of WordPress:
 *
 *   @require-wp-4.0
 *   Scenario: Core translation CRUD
 *
 * Then use in bash script:
 *
 *   BEHAT_TAGS=$(php behat-tags.php)
 *   vendor/bin/behat --format progress $BEHAT_TAGS
 */

function version_tags( $prefix, $current, $operator = '<' ) {
	if ( ! $current )
		return;

	exec( "grep '@{$prefix}-[0-9\.]*' -h -o features/*.feature | uniq", $existing_tags );

	$skip_tags = array();

	foreach ( $existing_tags as $tag ) {
		$compare = str_replace( "@{$prefix}-", '', $tag );
		if ( version_compare( $current, $compare, $operator ) ) {
			$skip_tags[] = $tag;
		}
	}

	return $skip_tags;
}

$skip_tags = array_merge(
	version_tags( 'require-wp', getenv( 'WP_VERSION' ), '<' ),
	version_tags( 'require-php', PHP_VERSION, '<' ),
	version_tags( 'less-than-php', PHP_VERSION, '>' )
);

if ( !empty( $skip_tags ) ) {
	echo '--tags=~' . implode( '&&~', $skip_tags );
}

