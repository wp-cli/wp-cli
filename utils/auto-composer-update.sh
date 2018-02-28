#!/bin/bash

###
# Runs composer update, commits changes to a new branch,
# and creates a pull request.
#
# Requires git, composer, and hub
###

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
# Run composer update and capture to untracked log file
composer update --no-progress --no-interaction |& tee vendor/update.log
UPDATE=$(cat vendor/update.log | col -b)

# We only care to proceed when there are changes
if [ -z "$(git status -s)" ]; then
	echo 'No updates available'
	exit 0;
fi

# Create a dated branch and commit the changes
DATE=$(date +%Y-%m-%d)
BRANCH="update-deps-$DATE"
git branch -f $BRANCH master
git checkout $BRANCH
git add .
MESSAGE="Update Composer dependencies ($DATE)

\`\`\`
$UPDATE
\`\`\`"
git commit -n -m "$MESSAGE"

# Push and pull request
git push origin $BRANCH
hub pull-request -m "$MESSAGE"
