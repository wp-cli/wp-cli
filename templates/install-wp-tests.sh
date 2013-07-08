#!/usr/bin/env bash

if [ $# -lt 3 ]; then
	echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version]"
	exit 1
fi

DB_NAME=$1
DB_USER=$2
DB_PASS=$3
DB_HOST=${4-localhost}
WP_VERSION=${5-master}

set -ex

# set up a WP install
WP_CORE_DIR=/tmp/wordpress/
mkdir -p $WP_CORE_DIR
wget -nv -O /tmp/wordpress.tar.gz https://github.com/WordPress/WordPress/tarball/$WP_VERSION
tar --strip-components=1 -zxmf /tmp/wordpress.tar.gz -C $WP_CORE_DIR

# set up testing suite
svn co --ignore-externals --quiet http://unit-tests.svn.wordpress.org/trunk/ $WP_TESTS_DIR

# portable in-place argument for both GNU sed and Mac OSX sed
if [[ $(uname -s) == 'Darwin' ]]; then
  ioption='-i ""'
else
  ioption='-i'
fi

# generate testing config file
cd $WP_TESTS_DIR
cp wp-tests-config-sample.php wp-tests-config.php
sed $ioption "s:dirname( __FILE__ ) . '/wordpress/':'$WP_CORE_DIR':" wp-tests-config.php
sed $ioption "s/yourdbnamehere/$DB_NAME/" wp-tests-config.php
sed $ioption "s/yourusernamehere/$DB_USER/" wp-tests-config.php
sed $ioption "s/yourpasswordhere/$DB_PASS/" wp-tests-config.php
sed $ioption "s|localhost|${DB_HOST}|" wp-tests-config.php

# parse DB_HOST for port or socket references
PARTS=(${DB_HOST//\:/ })
DB_HOSTNAME=${PARTS[0]};
DB_SOCK_OR_PORT=${PARTS[1]};
EXTRA=""

if ! [ -z $DB_HOSTNAME ] ; then
  if [[ "$DB_SOCK_OR_PORT" =~ ^[0-9]+$ ]] ; then
    EXTRA=" --host=$DB_HOSTNAME --port=$DB_SOCK_OR_PORT --protocol=tcp"
  elif ! [ -z $DB_SOCK_OR_PORT ] ; then
    EXTRA=" --socket=$DB_SOCK_OR_PORT"
  elif ! [ -z $DB_HOSTNAME ] ; then
    EXTRA=" --host=$DB_HOSTNAME --protocol=tcp"
  fi
fi

# create database
mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA
