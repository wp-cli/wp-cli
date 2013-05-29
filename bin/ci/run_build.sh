#!/bin/bash

set -ex

vendor/bin/phpunit

if [ -z "$WITH_RONN" ]; then
	BEHAT_OPTS="--tags ~@ronn"
fi

vendor/bin/behat --format progress $BEHAT_OPTS
