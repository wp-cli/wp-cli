<?php
/**
 * Due to PHP 5.6 compatibility, we have two different implementations of this class.
 *
 * See https://github.com/wp-cli/package-command/issues/172.
 */

use Composer\Semver\VersionParser;
use Composer\InstalledVersions;

if ( InstalledVersions::satisfies( new VersionParser(), 'composer/composer', '^2.3' ) ) {
	require 'ComposerIOWithTypes.php';
} else {
	require 'ComposerIOWithoutTypes.php';
}
