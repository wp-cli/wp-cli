<?php

function php_version_tags() {
	exec( 'grep "@require-php-[0-9\.]*" -h -o features/*.feature | uniq', $existing_tags );
}

function version_tags( $prefix, $current ) {
	if ( ! $current )
		return;

	exec( "grep '@{$prefix}-[0-9\.]*' -h -o features/*.feature | uniq", $existing_tags );

	$skip_tags = array();

	foreach ( $existing_tags as $tag ) {
		$required = str_replace( "@{$prefix}-", '', $tag );
		if ( version_compare( $current, $required, '<' ) ) {
			$skip_tags[] = $tag;
		}
	}

	return $skip_tags;
}

$skip_tags = array_merge(
	version_tags( 'require-wp', getenv( 'WP_VERSION' ) ),
	version_tags( 'require-php', PHP_VERSION )
);

# Skip Github API tests by default because of rate limiting. See https://github.com/wp-cli/wp-cli/issues/1612
$skip_tags[] = '@github-api';

if ( !empty( $skip_tags ) ) {
	echo '--tags=~' . implode( '&&~', $skip_tags );
}

