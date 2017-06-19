#!/bin/bash

set -ex

# Run CodeSniffer
phpcs --standard=phpcs.ruleset.xml $(find . -name '*.php' -not -path "./vendor/*" -not -path "./packages/*")

# Run the unit tests
vendor/bin/phpunit

BEHAT_TAGS=$(php ci/behat-tags.php)

# Run the functional tests
vendor/bin/behat --format progress $BEHAT_TAGS --strict

# Run the opcache save comments disabled tests
if [[ ! -z "$WP_CLI_TEST_OPCACHE_SAVE_COMMENTS_DISABLED" ]]; then
	BEHAT_TAGS=$(WP_CLI_DISABLE_OPCACHE_SAVE_COMMENTS=1 php ci/behat-tags.php)
	WP_CLI_PHP_ARGS='-dopcache.enable_cli=1 -dopcache.save_comments=0' vendor/bin/behat --format progress "$BEHAT_TAGS&&~@require-opcache-save-comments" --strict
fi
