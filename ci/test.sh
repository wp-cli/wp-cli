#!/bin/bash

set -ex

# Run CodeSniffer
phpcs

# Run the unit tests
phpunit --stop-on-failure

BEHAT_TAGS=$(php ci/behat-tags.php)

# Run the functional tests
vendor/bin/behat --format progress $BEHAT_TAGS --stop-on-failure --strict
