#!/bin/bash

###
# Checks out latest WP-CLI bundle and runs tests.
# Creates an issue if tests fail.
#
# Requires curl, git and composer
###

json_escape () {
    printf '%s' $1 | php -r 'echo json_encode(file_get_contents("php://stdin"));'
}

date

if [ -z "$WP_CLI_DIR" ]; then
	echo 'Please set $WP_CLI_DIR'
	exit 1
fi

set -ex

cd $WP_CLI_DIR

# Reset WP-CLI directory to baseline
git checkout -f master
git pull origin master
composer install

set -ex

BEHAT_TAGS=$(php ci/behat-tags.php)

REPOS=$(cat composer.json | grep -m 1 -oE "wp-cli/([a-z\-]*)-command")

for REPO in $REPOS; do

# Remove leading "wp-cli/".
COMMAND=${REPO#*/}

# Run the functional tests
vendor/bin/behat --format progress $BEHAT_TAGS --strict -p=$REPO  | tee tests/$COMMAND.log

if [ $? -ne 5 ]; then
	OUTPUT=$(awk '{printf "%s\\n", $0}' tests/$COMMAND.log)
	OUTPUT=$(json_escape "$OUTPUT")
	curl -X POST -H 'Content-type: application/json' \
	--data "{\"text\":\"Tests failed for $REPO (not really, only testing for now)\n\nOutput:\n\n$OUTPUT\"}" \
	https://hooks.slack.com/services/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
fi

done
