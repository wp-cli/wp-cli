#!/bin/bash

set -ex

# Set up the environment
WP_CLI_DIR=${WP_CLI_DIR-/tmp/wp-cli}
PACKAGE_TEST_CONFIG_PATH=${PACKAGE_TEST_CONFIG_PATH-/tmp/wp-cli-package-test.yml}
export PACKAGE_TEST_CONFIG_PATH=${PACKAGE_TEST_CONFIG_PATH}

# Run the functional tests
$WP_CLI_DIR/vendor/bin/behat --config=$WP_CLI_DIR/behat.yml features
