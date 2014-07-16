#!/bin/bash

set -ex

# Run the unit tests
vendor/bin/phpunit

# Run the functional tests
vendor/bin/behat --format progress

# Run the package tests against a direct clone of WP-CLI
WP_CLI_BIN_DIR='' php behat.phar --format progress features/package.feature

# Run CodeSniffer
./codesniffer/scripts/phpcs --standard=./ci/ php/
