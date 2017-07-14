#!/bin/bash
#
# Package WP-CLI to be installed on RPM-based systems.
#
# VERSION       :0.1.0
# DATE          :2017-07-12
# AUTHOR        :Viktor Szépe <viktor@szepe.net>
# LICENSE       :The MIT License (MIT)
# URL           :https://github.com/wp-cli/wp-cli/tree/master/utils
# BASH-VERSION  :4.2+
# DEPENDS       :apt-get install rpm rpmlint php-cli

PHAR_URL="https://github.com/wp-cli/builds/raw/gh-pages/phar/wp-cli.phar"
# Source directory
SOURCE_DIR="rpm-src"

die() {
    local RET="$1"
    shift

    echo -e "$@" >&2
    exit "$RET"
}

set -e

# Check dependencies
if ! hash php rpm; then
    die 1 "Missing RPM build tools"
fi

if ! [ -d "$SOURCE_DIR" ]; then
    mkdir "$SOURCE_DIR" || die 2 "Cannot create directory here: ${PWD}"
fi

pushd "$SOURCE_DIR" > /dev/null

# Download the binary
wget -nv -O wp-cli.phar "$PHAR_URL"
chmod +x wp-cli.phar

# Copy spec file
cp ../wp-cli-rpm.spec wp-cli.spec

# Replace version placeholder
WPCLI_VER="$(php wp-cli.phar cli version | cut -d " " -f 2)"
if [ -z "$WPCLI_VER" ]; then
    die 3 "Cannot get WP_CLI version"
fi
echo "Current version: ${WPCLI_VER}"
sed -i -e "s/^Version: .*\$/Version:    ${WPCLI_VER}/" wp-cli.spec || die 4 "Version update failed"
sed -i -e "s/^\(\* .*\) 0\.0\.0-1\$/\1 ${WPCLI_VER}-1/" wp-cli.spec || die 5 "Changleog update failed"

# Create man page
{
    echo '.TH "WP" "1"'
    php wp-cli.phar --help
} \
    | sed -e 's/^\([A-Z ]\+\)$/.SH "\1"/' \
    | sed -e 's/^  wp$/wp \\- The command line interface for WordPress/' \
    > wp.1

# Build the package
rpmbuild --define "_sourcedir ${PWD}" --define "_rpmdir ${PWD}" -bb wp-cli.spec | tee wp-cli-updaterpm-rpmbuild.$$.log

rpm_path=`grep -o "/.*/noarch/wp-cli-.*noarch.rpm" wp-cli-updaterpm-rpmbuild.$$.log`

rm -f wp-cli-updaterpm-rpmbuild.$$.log

if [ ${#rpm_path} -lt 20 ] ; then
	echo "RPM path doesn't exist ($rpm_path)"
	exit
fi

if [[ $(type -P "rpmlint") ]] ; then
	echo "Using rpmlint to check for errors"
# Run linter
cat <<"EOF" > rpmlint.config
setOption("CompressExtension", "gz")
addFilter(": E: no-packager-tag")
addFilter(": E: no-signature")
addFilter(": E: no-dependency-on locales-cli")
EOF

	rpmlint -v -f rpmlint.config -i $rpm_path || true

elif ([ $(type -P "rpm2cpio") ] && [ $(type -P "cpio") ]); then
	echo "No RPM lint found $rpm_path .. using alternative method"
	mkdir rpm-test-$$
	cd rpm-test-$$
	if [ $? -ne 0 ] ; then
		echo "Failed to cd into rpm-test-$$"
		exit;
	fi
	rpm2cpio $rpm_path | cpio -idmv

	if [ -f "usr/bin/wp" ] ; then
		echo "RPM test succeeded"
	else 
		echo "RPM test failed"
	fi
	rm -rfv ../rpm-test-$$
else
	echo "All test methods failed"
fi


popd > /dev/null

echo "OK."
