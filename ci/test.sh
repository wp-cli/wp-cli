#!/bin/bash

set -ex

export WP_CLI_BIN_DIR=/tmp/wp-cli-phar

# Run the unit tests
vendor/bin/phpunit

BEHAT_TAGS=$(php ci/behat-tags.php)

# Run the functional tests
vendor/bin/behat --format progress $BEHAT_TAGS

# Run CodeSniffer
./codesniffer/scripts/phpcs --standard=./ci/ php/
