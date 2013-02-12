<?php

// php -dphar.readonly=0 utils/make-phar.php

$iterator = new \RecursiveIteratorIterator(
	new \RecursiveDirectoryIterator( './php', FilesystemIterator::SKIP_DOTS )
);

$phar = new Phar( 'wp-cli.phar', 0, 'wp-cli.phar' );

$phar->startBuffering();

foreach ( $iterator as $path ) {
	if ( !preg_match( '/\.php$/', $path ) )
		continue;

	$key = str_replace( './', '', $path );

	echo "$key - $path\n";

	$phar[ $key ] = file_get_contents( $path );
}

$phar->setStub( <<<EOB
<?php
Phar::mapPhar();
include 'phar://wp-cli.phar/php/boot-phar.php';
__HALT_COMPILER();
?>
EOB
);

$phar->stopBuffering();

echo "Generated wp-cli.phar.\n";

