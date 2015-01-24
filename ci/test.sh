#!/bin/bash

set -ex

# Run the unit tests
# vendor/bin/phpunit

# eval $(./ci/set-behat-tags.sh)

# Run the functional tests
vendor/bin/behat features/cli.feature --format progress --tags="debug"

# Run CodeSniffer
# ./codesniffer/scripts/phpcs --standard=./ci/ php/
