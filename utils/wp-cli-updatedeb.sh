#!/bin/bash
#
# Package wp-cli to be installed in Debian-compatible systems.
# Only the phar file is included.
#
# VERSION       :0.2.4
# DATE          :2017-05-31
# AUTHOR        :Viktor Szépe <viktor@szepe.net>
# LICENSE       :The MIT License (MIT)
# URL           :https://github.com/wp-cli/wp-cli/tree/master/utils
# BASH-VERSION  :4.2+

# packages source path
DIR="php-wpcli"
# phar URL
PHAR="https://github.com/wp-cli/builds/raw/gh-pages/phar/wp-cli.phar"

die() {
    local RET="$1"
    shift

    echo -e "$@" >&2
    exit "$RET"
}

dump_control() {
    cat > DEBIAN/control <<EOF
Package: php-wpcli
Version: 0.0.0
Architecture: all
Maintainer: Daniel Bachhuber <daniel@handbuilt.co>
Section: php
Priority: optional
Depends: php5-cli (>= 5.3.29) | php-cli | php7-cli, php5-mysql | php5-mysqlnd | php7.0-mysql | php7.1-mysql, mysql-client | mariadb-client
Homepage: http://wp-cli.org/
Description: wp-cli is a set of command-line tools for managing
 WordPress installations. You can update plugins, set up multisite
 installations and much more, without using a web browser.

EOF
}

set -e

# deb's dir
if ! [ -d "$DIR" ]; then
    mkdir "$DIR" || die 1 "Cannot create directory here: ${PWD}"
fi

pushd "$DIR"

# control file
if ! [ -r DEBIAN/control ]; then
    mkdir DEBIAN
    dump_control
fi

# copyright
if ! [ -r usr/share/doc/php-wpcli/copyright ]; then
    mkdir -p usr/share/doc/php-wpcli &> /dev/null
    wget -nv -O usr/share/doc/php-wpcli/copyright https://github.com/wp-cli/wp-cli/raw/master/LICENSE
fi

# changelog
if ! [ -r usr/share/doc/php-wpcli/changelog.gz ]; then
    mkdir -p usr/share/doc/php-wpcli &> /dev/null
    echo "Changelog can be found in the blog: https://make.wordpress.org/cli/" \
        | gzip -n -9 > usr/share/doc/php-wpcli/changelog.gz
fi

# content dirs
[ -d usr/bin ] || mkdir -p usr/bin

# download current version
wget -nv -O usr/bin/wp "$PHAR" || die 3 "Phar download failure"
chmod +x usr/bin/wp || die 4 "chmod failure"

# get version
WPCLI_VER="$(usr/bin/wp cli version | cut -d " " -f 2)"
[ -z "$WPCLI_VER" ] && die 5 "Cannot get wp-cli version"
echo "Current version: ${WPCLI_VER}"

# update version
sed -i -e "s/^Version: .*$/Version: ${WPCLI_VER}/" DEBIAN/control || die 6 "Version update failure"

# minimal man page
if ! [ -r usr/share/man/man1/wp.1.gz ]; then
    mkdir -p usr/share/man/man1 &> /dev/null
    {
        echo '.TH "WP" "1"'
        usr/bin/wp --help
    } \
        | sed 's/^\([A-Z ]\+\)$/.SH "\1"/' \
        | sed 's/^  wp$/wp \\- A command line interface for WordPress/' \
        | gzip -n -9 > usr/share/man/man1/wp.1.gz
fi

# update MD5-s
find usr -type f -exec md5sum "{}" ";" > DEBIAN/md5sums || die 7 "md5sum creation failure"

popd

# build package in the current diretory
WPCLI_PKG="${PWD}/php-wpcli_${WPCLI_VER}_all.deb"
fakeroot dpkg-deb --build "$DIR" "$WPCLI_PKG" || die 8 "Packaging failed"

# check package - not critical
lintian --display-info --display-experimental --pedantic --show-overrides php-wpcli_*_all.deb || true

# optional steps
echo "sign it:               dpkg-sig -k SIGNING-KEY -s builder \"${WPCLI_PKG}\""
echo "include in your repo:  pushd /var/www/REPO-DIR"
echo "                       reprepro includedeb jessie \"${WPCLI_PKG}\" && popd"
