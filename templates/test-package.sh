#!/bin/bash

set -ex

# Set up the environment
WP_CLI_DIR=${WP_CLI_DIR-/tmp/wp-cli}
PACKAGE_TEST_CONFIG_PATH=${PACKAGE_TEST_CONFIG_PATH-/tmp/wp-cli-package-test.yml}
PACKAGE_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )"/../ && pwd )"
export PACKAGE_TEST_CONFIG_PATH=${PACKAGE_TEST_CONFIG_PATH}

if [ ! -z "$1" ]
  then
    FEATURES_DIR="$1"
else
    FEATURES_DIR="$PACKAGE_DIR/features"
fi

# Run the functional tests
$WP_CLI_DIR/vendor/bin/behat --config=$WP_CLI_DIR/behat.yml $FEATURES_DIR
