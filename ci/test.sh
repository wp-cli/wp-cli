#!/bin/bash

set -ex

# Run CodeSniffer
vendor/bin/phpcs

# Run the unit tests
phpunit

BEHAT_TAGS=$(php ci/behat-tags.php)

# Run the functional tests
vendor/bin/behat --format progress $BEHAT_TAGS --strict
