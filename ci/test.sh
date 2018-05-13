#!/bin/bash

set -ex

BEHAT_TAGS=$(php ci/behat-tags.php)

# Run the functional tests
echo vendor/bin/behat $BEHAT_TAGS --strict features/cli.feature


wp package install wp-cli/scaffold-package-command
wget https://github.com/wp-cli/builds/raw/gh-pages/phar/wp-cli-nightly.phar

php wp-cli-nightly.phar package install wp-cli/scaffold-package-command
