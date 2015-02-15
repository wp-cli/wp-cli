#!/bin/bash
#
# Package wp-cli to be installed in Debian-compatible systems.
# Only the phar file is included.
#
# VERSION       :0.2
# DATE          :2014-11-19
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
    cat > DEBIAN/control <<CTRL
Package: php-wpcli
Version: 0.0.0
Architecture: all
Maintainer: Daniel Bachhuber <daniel@handbuilt.co>
Section: php
Priority: optional
Depends: php5-cli, php5-mysql | php5-mysqlnd, mysql-client
Homepage: http://wp-cli.org/
Description: wp-cli is a set of command-line tools for managing
 WordPress installations. You can update plugins, set up multisite
 installs and much more, without using a web browser.

CTRL
}

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

# content dirs
[ -d usr/bin ] || mkdir -p usr/bin

# download current version
wget -nv -O usr/bin/wp "$PHAR" || die 3 "Phar download failure"
chmod +x usr/bin/wp || die 4 "chmod failure"

# get version
WPCLI_VER="$(grep -ao "define.*WP_CLI_VERSION.*;" usr/bin/wp | cut -d"'" -f4)"
[ -z "$WPCLI_VER" ] && die 5 "Cannot get wp-cli version"
echo "Current version: ${WPCLI_VER}"

# update version
sed -i "s/^Version: .*$/Version: ${WPCLI_VER}/" DEBIAN/control || die 6 "Version update failure"

# update MD5-s
find usr -type f -exec md5sum \{\} \; > DEBIAN/md5sums || die 7 "md5sum creation failure"

popd

# build package in the current diretory
WPCLI_PKG="${PWD}/php-wpcli_${WPCLI_VER}_all.deb"
fakeroot dpkg-deb --build "$DIR" "$WPCLI_PKG" || die 8 "Packaging failed"

# optional steps
echo "sign it:  dpkg-sig -k <YOUR-KEY> -s builder \"$WPCLI_PKG\""
echo "include in your repo:  pushd /var/www/<REPO-DIR>"
echo "reprepro includedeb wheezy \"$WPCLI_PKG\" && popd"
