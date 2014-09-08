#!/bin/bash

# http://stackoverflow.com/questions/4023830/bash-how-compare-two-strings-in-version-format
vercomp() {
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
        require=${requires[$i]}
        vercomp $version $WP_VERSION
        compare="$?"
        if [[ 1 == $compare ]]; then
            skip_tags="$skip_tags~$require,"
        fi
    done
    if [[ "--tags='" != $skip_tags ]]; then
        skip_tags=$(echo $skip_tags| sed 's/\,$//') # trim trailing ','
        skip_tags="$skip_tags'" # close the argument
    else
        skip_tags=''
    fi

fi

echo export behat_tags=$skip_tags