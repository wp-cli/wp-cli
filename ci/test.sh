#!/bin/bash

set -ex

cat ci/script.gdb | sudo gdb -args ~/.phpenv/versions/$(phpenv version-name)/bin/php ~/.phpenv/versions/$(phpenv version-name)/bin/phpunit

# Run the unit tests
# vendor/bin/phpunit

# Run the functional tests
vendor/bin/behat --format progress features/media.feature

# Run CodeSniffer
# ./codesniffer/scripts/phpcs --standard=./ci/ php/
