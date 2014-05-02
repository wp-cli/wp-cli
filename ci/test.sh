#!/bin/bash

set -ex

# Run the unit tests
# vendor/bin/phpunit

# Run the functional tests
gdb --args ./vendor/bin/behat --format progress features/media.feature

# Run CodeSniffer
# ./codesniffer/scripts/phpcs --standard=./ci/ php/
