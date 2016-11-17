#!/bin/bash

# called by Travis CI

set -ex

WP_CLI_BIN_DIR=${WP_CLI_BIN_DIR-/tmp/wp-cli-phar}

composer install --no-interaction --prefer-source

CLI_VERSION=$(head -n 1 VERSION)
if [[ $CLI_VERSION == *"-alpha"* ]]
then
	GIT_HASH=$(git rev-parse HEAD)
	GIT_SHORT_HASH=${GIT_HASH:0:7}
	CLI_VERSION="$CLI_VERSION-$GIT_SHORT_HASH"
fi

# the Behat test suite will pick up the executable found in $WP_CLI_BIN_DIR
if [[ $BUILD == 'git' ]]
then
	echo $CLI_VERSION > VERSION
else
	mkdir -p $WP_CLI_BIN_DIR
	php -dphar.readonly=0 utils/make-phar.php wp-cli.phar --quiet --version=$CLI_VERSION
	mv wp-cli.phar $WP_CLI_BIN_DIR/wp
	chmod +x $WP_CLI_BIN_DIR/wp
fi

echo $CLI_VERSION > PHAR_BUILD_VERSION

# Install CodeSniffer things
./ci/prepare-codesniffer.sh

mysql -e 'CREATE DATABASE wp_cli_test;' -uroot
mysql -e 'GRANT ALL PRIVILEGES ON wp_cli_test.* TO "wp_cli_test"@"localhost" IDENTIFIED BY "password1"' -uroot
