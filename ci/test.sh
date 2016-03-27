#!/bin/bash

set -ex

# Run the unit tests
vendor/bin/phpunit

BEHAT_TAGS=$(php ci/behat-tags.php)
#export WP_CLI_MYSQL_URL='mysql://wp_cli_test:password1@127.0.0.1:/wp_cli_test'

# Run the functional tests
vendor/bin/behat --format progress $BEHAT_TAGS

# Run CodeSniffer
./codesniffer/scripts/phpcs --standard=./ci/ php/
