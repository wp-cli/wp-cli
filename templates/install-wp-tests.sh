#!/usr/bin/env bash


if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
DB_EXISTS=0
WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=/tmp/wordpress/
HAS_CURL=0

set -e

# Some boilerplate checks and notices

precheck() {

	if ! hash wget 2>/dev/null ; then
		echo "This requires wget to be installed"
		exit
	fi

	if hash curl 2>/dev/null ; then
		HAS_CURL=1
	fi

	echo "This script installs an instance of WordPress to use in your unit testing. The script will only work if one of the following are true:" 
	echo ""
	echo "- $DB_NAME exists, is empty and $DB_USER/$DB_PASS has write permissions to it"
	echo "                                 -or-"
	echo "- $DB_NAME *does not* exist and $DB_USER/$DB_PASS has permissions to create it"
	echo ""
}

# if the user has curl installed, check that the $HTTP_TAR_FILE returns a 200, if it doesn't return a 200, error out with a message, otherwise continue
# if the user does not have cur installed, move on

check_http_status() {
	if [ "$HAS_CURL" != 1 ]; then
		echo "curl not installed, skipping check if $HTTP_TAR_FILE exists"
	else
		echo "curl is installed, let's check if $HTTP_TAR_FILE exists"
		status_code=$(curl -o /dev/null --silent --head --write-out '%{http_code}\n' $HTTP_TAR_FILE)
		if [ $status_code -ne "200" ]; then
			echo "Received an error when trying to download '$HTTP_TAR_FILE', make sure you entered a correct version"
			exit
		fi
		echo "It returns a 200, we're good here, let's move on."
	fi

}

# actual installation of wordpress

install_wp() {

	mkdir -p $WP_CORE_DIR

	if [ $WP_VERSION == 'latest' ]; then 

		local ARCHIVE_NAME='latest'
	else
		local ARCHIVE_NAME="wordpress-$WP_VERSION"
	fi
	HTTP_TAR_FILE="https://wordpress.org/${ARCHIVE_NAME}.tar.gz"

	check_http_status #check if $HTTP_TAR_FILE returns a 200

	wget -nv -O /tmp/wordpress.tar.gz $HTTP_TAR_FILE
	tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C $WP_CORE_DIR

	wget -nv -O $WP_CORE_DIR/wp-content/db.php https://raw.github.com/markoheijnen/wp-mysqli/master/db.php
}

# installation of test suite
install_test_suite() {
	# portable in-place argument for both GNU sed and Mac OSX sed
	if [[ $(uname -s) == 'Darwin' ]]; then
		local ioption='-i .bak'
	else
		local ioption='-i'
	fi

	# set up testing suite
	mkdir -p $WP_TESTS_DIR
	cd $WP_TESTS_DIR
	svn co --quiet http://develop.svn.wordpress.org/trunk/tests/phpunit/includes/

	wget -nv -O wp-tests-config.php http://develop.svn.wordpress.org/trunk/wp-tests-config-sample.php
	sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR':" wp-tests-config.php
	sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" wp-tests-config.php
	sed $ioption "s/yourusernamehere/$DB_USER/" wp-tests-config.php
	sed $ioption "s/yourpasswordhere/$DB_PASS/" wp-tests-config.php
	sed $ioption "s|localhost|${DB_HOST}|" wp-tests-config.php
}

# setup the database

install_db() {
	# parse DB_HOST for port or socket references
	local PARTS=(${DB_HOST//\:/ })
	local DB_HOSTNAME=${PARTS[0]};
	local DB_SOCK_OR_PORT=${PARTS[1]};
	local EXTRA=""

	if ! [ -z $DB_HOSTNAME ] ; then
		if [[ "$DB_SOCK_OR_PORT" =~ ^[0-9]+$ ]] ; then
			EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
		elif ! [ -z $DB_SOCK_OR_PORT ] ; then
			EXTRA=" --socket=$DB_SOCK_OR_PORT"
		elif ! [ -z $DB_HOSTNAME ] ; then
			EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
		fi
	fi

	mysqlshow $DB_NAME > /dev/null 2>&1 && DB_EXISTS=1

	if [ $DB_EXISTS == 1 ]; then
		echo "Database already exists, moving on..."
	else
		echo "Creating database"
		# create database
		mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
	fi

	if [ $WP_VERSION == 'master' ]; then
		WP_VERSION='latest version'
	fi
	echo "Installed WordPress $WP_VERSION in $WP_TESTS_DIR, you are ready to test."
}




precheck
install_wp
install_test_suite
install_db
