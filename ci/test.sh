#!/bin/bash

set -ex

# Run CodeSniffer
phpcs --standard=phpcs.ruleset.xml $(find . -name '*.php' -not -path "./vendor/*" -not -path "./packages/*")

# Run the unit tests
vendor/bin/phpunit

BEHAT_TAGS=$(php ci/behat-tags.php)

# Run the functional tests
vendor/bin/behat --format progress $BEHAT_TAGS --strict
