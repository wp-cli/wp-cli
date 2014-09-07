#!/bin/bash

set -ex

# Run the unit tests
vendor/bin/phpunit

# http://stackoverflow.com/questions/4023830/bash-how-compare-two-strings-in-version-format
function vercomp () {
	echo $1
	echo $2
    if [[ $1 == $2 ]]
    then
        return 0
    fi
    local IFS=.
    local i ver1=($1) ver2=($2)
    # fill empty fields in ver1 with zeros
    for ((i=${#ver1[@]}; i<${#ver2[@]}; i++))
    do
        ver1[i]=0
    done
    for ((i=0; i<${#ver1[@]}; i++))
    do
        if [[ -z ${ver2[i]} ]]
        then
            # fill empty fields in ver2 with zeros
            ver2[i]=0
        fi
        if ((10#${ver1[i]} > 10#${ver2[i]}))
        then
            return 1
        fi
        if ((10#${ver1[i]} < 10#${ver2[i]}))
        then
            return 2
        fi
    done
    return 0
}

if [[ ! -z "$WP_VERSION" ]]; then

    skip_tags="--tags='"
    requires=($(grep "@require-wp-[0-9\.]*" -h -o features/*.feature | uniq))
    for (( i = 0; i < ${#requires[@]}; i++ )); do
        version=${requires[$i]:12}
        comp=$(vercomp $version $WP_VERSION)
        if [[ 1 == $comp ]]; then
            skip_tags="$skip_tags~$tag,"
        fi
    done
    skip_tags="$skip_tags'"

fi

# Run the functional tests
vendor/bin/behat --format progress $skip_tags

# Run CodeSniffer
./codesniffer/scripts/phpcs --standard=./ci/ php/
