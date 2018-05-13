#!/bin/bash

set -ex

BEHAT_TAGS=$(php ci/behat-tags.php)

# Run the functional tests
echo vendor/bin/behat $BEHAT_TAGS --strict features/cli.feature

wget https://github.com/wp-cli/builds/raw/gh-pages/phar/wp-cli-nightly.phar

php wp-cli-nightly.phar package install wp-cli/scaffold-package-command
find /home/travis/.wp-cli/ -type f
php wp-cli-nightly.phar cli has-command scaffold package
