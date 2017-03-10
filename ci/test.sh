#!/bin/bash

set -ex

# Run the unit tests
vendor/bin/phpunit

BEHAT_TAGS=$(php ci/behat-tags.php)

# Run the functional tests
FEATURES=features/*.feature
for f in $FEATURES
do
	vendor/bin/behat $f --format progress $BEHAT_TAGS --strict
done

# Run CodeSniffer
./codesniffer/scripts/phpcs --standard=./ci/ php/
