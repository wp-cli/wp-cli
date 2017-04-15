#!/usr/bin/env bash

find_php() {
	read -r -d '' AMP_PATHS <<EOB
/Applications/MAMP/bin/php/*/bin/php
/Applications/xampp/xamppfiles/bin/php
/opt/lampp/bin/php
EOB

	# Special case for *AMP installers, since they normally don't set themselves
	# as the default cli php out of the box.
	for amp_php in $AMP_PATHS; do
		if [[ "$amp_php" == *php5.2* ]]; then
			continue
		fi

		if [ -x $amp_php ]; then
			echo $amp_php
			exit
		fi
	done

	which php || which php-cli || return 1
}

INSTALL_DIR=$1
if [ -z "$INSTALL_DIR" ]; then
	INSTALL_DIR=$HOME/.composer
fi

VERSION=$2
if [ -z "$VERSION" ]; then
	VERSION='@stable'
fi

# Find a PHP binary
if [ -z "$WP_CLI_PHP" ]; then
	WP_CLI_PHP=`find_php`
	if [ $? -gt 0 ]; then
		read -p "path to PHP binary: " WP_CLI_PHP
	fi
else
	command -v $WP_CLI_PHP > /dev/null || {
		echo "invalid PHP binary: $WP_CLI_PHP" 1>&2
		exit 1
	}
fi

mkdir -p "$INSTALL_DIR"
cd "$INSTALL_DIR"

# install Composer
if [ ! -x composer.phar ]; then
	curl -sS https://getcomposer.org/installer | $WP_CLI_PHP
	if [ $? -gt 0 ]; then
		exit 1
	fi
fi

# set up global composer.json file
if [ ! -f composer.json ]; then
	cat > composer.json <<EOB
{
	"minimum-stability": "dev",
	"config": {
		"vendor-dir": "vendor",
		"bin-dir": "bin"
	},
	"require": {
	}
}
EOB
fi

COMPOSER="$WP_CLI_PHP composer.phar --prefer-source" 

command -v bin/wp > /dev/null || {
	echo
	echo "Installing the main WP-CLI package..."
	echo "-------------------------------------"
	$COMPOSER require wp-cli/wp-cli="$VERSION"
}

command -v bin/boris > /dev/null || {
	echo
	printf "Trying to install the optional Boris package... "
	$COMPOSER --quiet require 'd11wtq/boris=@stable'
	if [ $? -gt 0 ]; then
		echo "failed."
	else
		echo "done."
	fi
}

cat <<EOB

WP-CLI files have been succesfully installed.

To test, run:

	$INSTALL_DIR/bin/wp --info
EOB

if [ "$WP_CLI_PHP" != "$(which php)" ]; then
	cat <<EOB

Make sure you have the following line in your .bashrc file:

	export WP_CLI_PHP=$WP_CLI_PHP
EOB
fi
