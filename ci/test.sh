#!/bin/bash

set -ex

# Run the unit tests
vendor/bin/phpunit

# Run the functional tests
vendor/bin/behat --format progress

# Run CodeSniffer
./codesniffer/scripts/phpcs --standard=./ci/ php/
