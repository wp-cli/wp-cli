#!/bin/bash

set -ex

# Run the unit tests
# vendor/bin/phpunit

# Run the functional tests
gdb --eval-command="set env MALLOC_CHECK_=3" --eval-command=run --eval-command="backtrace full" --eval-command=quit  --args "bash vendor/bin/behat --format progress features/media.feature"

# Run CodeSniffer
# ./codesniffer/scripts/phpcs --standard=./ci/ php/
