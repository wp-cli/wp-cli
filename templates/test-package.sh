#!/bin/bash

set -ex

# Set up the environment
WP_CLI_DIR=${WP_CLI_DIR-/tmp/wp-cli}

# Run the functional tests
./$WP_CLI_DIR}/behat.phar --config=$WP_CLI_DIR/behat.yml features
