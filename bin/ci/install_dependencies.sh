#!/bin/bash

# called by Travis CI

set -ex

# install dependencies
composer install --dev --no-interaction --prefer-source
composer require d11wtq/boris=dev-master --no-interaction --prefer-source

# set up WP install
./bin/wp core download --version=$WP_VERSION --path=/tmp/wp-cli-test-core-download-cache/

# set up database
mysql -e 'CREATE DATABASE wp_cli_test;' -uroot
mysql -e 'GRANT ALL PRIVILEGES ON wp_cli_test.* TO "wp_cli_test"@"localhost" IDENTIFIED BY "password1"' -uroot
