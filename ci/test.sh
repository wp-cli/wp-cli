#!/bin/bash

set -ex

# Run the unit tests
phpunit

if [ $WP_VERSION = "latest" ]; then
	export WP_VERSION=$(curl -s https://api.wordpress.org/core/version-check/1.7/ | jq -r ".offers[0].current")
fi

BEHAT_TAGS=$(php ci/behat-tags.php)

# Run the functional tests
vendor/bin/behat --format progress $BEHAT_TAGS --strict
