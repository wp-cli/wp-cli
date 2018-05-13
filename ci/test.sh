#!/bin/bash

set -ex

BEHAT_TAGS=$(php ci/behat-tags.php)

# Run the functional tests
vendor/bin/behat $BEHAT_TAGS --strict features/cli.feature
