<?php

# Skip Github API tests by default because of rate limiting. See https://github.com/wp-cli/wp-cli/issues/1612
$skip_tags = array('@github-api');

exec( 'grep "@require-wp-[0-9\.]*" -h -o features/*.feature | uniq', $existing_tags );

$WP_VERSION = getenv( 'WP_VERSION' );

if ( $WP_VERSION ) {
	foreach ( $existing_tags as $tag ) {
		$version = str_replace( '@require-wp-', '', $tag );
		if ( version_compare( $version, $WP_VERSION, '>' ) ) {
			$skip_tags[] = $tag;
		}
	}
}

if ( !empty( $skip_tags ) ) {
	echo '--tags=~' . implode( '&&~', $skip_tags );
}

