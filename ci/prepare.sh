#!/bin/bash

# called by Travis CI

set -ex

composer install --no-interaction --prefer-source

CLI_VERSION=$(head -n 1 VERSION)
if [[ $CLI_VERSION == *"-alpha"* ]]
then
	GIT_HASH=$(git rev-parse HEAD)
	GIT_SHORT_HASH=${GIT_HASH:0:7}
	CLI_VERSION="$CLI_VERSION-$GIT_SHORT_HASH"
fi

# the Behat test suite will pick up the executable found in $WP_CLI_BIN_DIR
mkdir -p $WP_CLI_BIN_DIR
php -dphar.readonly=0 utils/make-phar.php wp-cli.phar --quiet --version=$CLI_VERSION
mv wp-cli.phar $WP_CLI_BIN_DIR/wp
chmod +x $WP_CLI_BIN_DIR/wp

# Install CodeSniffer things
./ci/prepare-codesniffer.sh

if [[ $WP_VERSION == "trunk" ]]
then
	wget https://wordpress.org/nightly-builds/wordpress-latest.zip
	unzip wordpress-latest.zip
	mv wordpress '/tmp/wp-cli-test core-download-cache/'
else
	./bin/wp core download --version=$WP_VERSION --path='/tmp/wp-cli-test core-download-cache/'
fi

./bin/wp core version --path='/tmp/wp-cli-test core-download-cache/'

mysql -e 'CREATE DATABASE wp_cli_test;' -uroot
mysql -e 'GRANT ALL PRIVILEGES ON wp_cli_test.* TO "wp_cli_test"@"localhost" IDENTIFIED BY "password1"' -uroot
