#!/bin/bash

# called by Travis CI

if [[ "false" != "$TRAVIS_PULL_REQUEST" ]]; then
	echo "Not deploying pull requests."
	exit
fi

if [ -z $DEPLOY_BRANCH ]; then
	echo "Skipping deployment as DEPLOY_BRANCH is not set"
	exit
fi

if [[ "$TRAVIS_BRANCH" != "$DEPLOY_BRANCH" ]] && [[ ! "$TRAVIS_BRANCH" == "release-"* ]]; then
	echo "Skipping deployment as '$TRAVIS_BRANCH' is not a deploy branch."
	exit
fi

# Turn off command traces while dealing with the private key
set +x

# Get the encrypted private key from the repo settings
echo $WP_CLI_REPO_DEPLOY_KEY | base64 --decode > ~/.ssh/id_rsa
chmod 600 ~/.ssh/id_rsa

# anyone can read the build log, so it MUST NOT contain any sensitive data
set -x

# add github's public key
echo "|1|qPmmP7LVZ7Qbpk7AylmkfR0FApQ=|WUy1WS3F4qcr3R5Sc728778goPw= ssh-rsa AAAAB3NzaC1yc2EAAAABIwAAAQEAq2A7hRGmdnm9tUDbO9IDSwBK6TbQa+PXYPCPy6rbTrTtw7PHkccKrpp0yVhp5HdEIcKr6pLlVDBfOLX9QUsyCOV0wzfjIJNlGEYsdlLJizHhbn2mUjvSAHQqZETYP81eFzLQNnPHt4EVVUh7VfDESU84KezmD5QlWpXLmvU31/yMf+Se8xhHTvKSCZIFImWwoG6mbUoWf9nzpIoaSjB+weqqUUmpaaasXVal72J+UX2B+2RPW3RcT0eOzQgqlJL3RKrTJvdsjE3JEAvGq3lGHSZXy28G3skua2SmVi/w4yCE6gbODqnTWlg7+wC604ydGXA8VJiS5ap43JXiUFFAaQ==" >> ~/.ssh/known_hosts

git clone git@github.com:wp-cli/builds.git
mv PHAR_BUILD_VERSION builds/phar/NIGHTLY_VERSION
cd builds

git config user.name "Travis CI"
git config user.email "travis@travis-ci.org"
git config push.default "current"

if [[ "$TRAVIS_BRANCH" == "release-"* ]]; then
	fname="phar/wp-cli-release.phar"
else
	fname="phar/wp-cli-nightly.phar"
fi

mv /tmp/wp-cli-phar/wp $fname
chmod -x $fname

md5sum $fname | cut -d ' ' -f 1 > $fname.md5
sha512sum $fname | cut -d ' ' -f 1 > $fname.sha512

git add $fname $fname.md5 $fname.sha512 phar/NIGHTLY_VERSION
git commit -m "phar build: $TRAVIS_REPO_SLUG@$TRAVIS_COMMIT"

git push
