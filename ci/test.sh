#!/bin/bash

set -ex

# Run the unit tests
vendor/bin/phpunit

eval $(./ci/set-behat-tags.sh)

# Run the functional tests
vendor/bin/behat --format progress $behat_tags

# Run CodeSniffer
./codesniffer/scripts/phpcs --standard=./ci/ php/
