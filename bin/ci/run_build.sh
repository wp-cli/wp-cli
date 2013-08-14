#!/bin/bash

set -ex

phpunit

# Run the functional tests against the Phar file

WP_CLI_BIN_DIR=/tmp/wp-cli-phar

mkdir -p $WP_CLI_BIN_DIR
php -dphar.readonly=0 utils/make-phar.php wp-cli.phar --quiet
mv wp-cli.phar $WP_CLI_BIN_DIR/wp
chmod +x $WP_CLI_BIN_DIR/wp

WP_CLI_BIN_DIR=$WP_CLI_BIN_DIR php behat.phar --format progress
