#!/usr/bin/env bash

PACKAGE_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )"/../ && pwd )"
WP_CLI_DIR=${WP_CLI_DIR-/tmp/wp-cli}
PACKAGE_TEST_CONFIG_PATH="/tmp/wp-cli-package-test.yml"

set -ex

install_wp_cli_suite() {

	# Set up WP-CLI
	if [ ! -d $WP_CLI_DIR ]
		then
			git clone --quiet -b scaffold-package-tests https://github.com/wp-cli/wp-cli.git $WP_CLI_DIR
			cd $WP_CLI_DIR
			curl -sS https://getcomposer.org/installer | php
			chmod +x composer.phar
			./composer.phar install
	else
		cd $WP_CLI_DIR
		git pull
	fi
}

set_package_context() {

	cd $PACKAGE_DIR

	touch $PACKAGE_TEST_CONFIG_PATH
	printf 'require:' > $PACKAGE_TEST_CONFIG_PATH
	requires=$(php $WP_CLI_DIR/utils/get-package-require-from-composer.php composer.json)
	for require in "${requires[@]}"
	do
		printf "$config_file\n%2s-%1s$PACKAGE_DIR/$require" >> $PACKAGE_TEST_CONFIG_PATH
	done

}

install_db() {

	# For wp_cli_test too
	mysql -e 'CREATE DATABASE IF NOT EXISTS wp_cli_test;' -uroot
	mysql -e 'GRANT ALL PRIVILEGES ON wp_cli_test.* TO "wp_cli_test"@"localhost" IDENTIFIED BY "password1"' -uroot
}

install_wp_cli_suite
set_package_context
install_db
