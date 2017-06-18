#!/bin/bash

set -ex

# Run CodeSniffer
phpcs --standard=phpcs.ruleset.xml $(find . -name '*.php' -not -path "./vendor/*" -not -path "./packages/*")

# Run the unit tests
vendor/bin/phpunit

BEHAT_TAGS=$(php ci/behat-tags.php)

# Run the functional tests
if [[ -n "$SAVE_COMMENTS_DISABLED" ]]; then
	# Run the functional tests with opcache.save_comments disabled.
	WP_CLI_PHP_ARGS='-dopcache.enable_cli=1 -dopcache.save_comments=0' vendor/bin/behat --format progress "$BEHAT_TAGS&&~@require-opcache-save-comments" --strict
else
	vendor/bin/behat --format progress $BEHAT_TAGS --strict
fi
