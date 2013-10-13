#!/bin/bash

set -ex

# Run the unit tests
phpunit

# Run the functional tests
php behat.phar --format progress

# Run CodeSniffer
./codesniffer/scripts/phpcs \
	--standard=./ci/ --sniffs=Generic.CodeAnalysis.VariableAnalysis.UndefinedVariable \
	--ignore=php/Spyc.php php/
