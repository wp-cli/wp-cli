# Regular Release Checklist - v2.x.x

### Preparation

- [ ] Write release post on the [Make.org CLI blog](https://make.wordpress.org/cli/wp-admin/post-new.php)
- [ ] Regenerate command and internal API docs

    git clone git@github.com:wp-cli/handbook.git
    cd handbook
    wp handbook gen-all

- [ ] Verify results of [automated test suite](https://github.com/wp-cli/automated-tests)

### Updating WP-CLI

- [ ] Create a branch called `release-x-x-x` on `wp-cli/wp-cli-bundle` to prepare the release PR.

- [ ] Ensure that the contents of [VERSION](https://github.com/wp-cli/wp-cli/blob/master/VERSION) in `wp-cli/wp-cli` are changed to latest.

- [ ] Update the WP-CLI version mention in `wp-cli/wp-cli`'s `README.md` ([ref](https://github.com/wp-cli/wp-cli/issues/3647)).

- [ ] Lock `php-cli-tools` version (if needed)

- [ ] Lock the framework version in the ([bundle repository](https://github.com/wp-cli/wp-cli-bundle/))

### Updating the contributor list

- [ ] Fetch the list of contributors (from within the [`wp-cli/wp-cli-dev`](https://githubcom/wp-cli/wp-cli-dev/) project repo)

    GITHUB_TOKEN=<token> wp maintenance contrib-list --format=markdown

### Updating the Phar build

- [ ] Create a PR from the `release-x-x-x` branch in `wp-cli/wp-cli-bundle` and merge it. This will trigger the `wp-cli-release.*` builds.

- [ ] Create a git tag and push it.

- [ ] Create a stable [Phar build](https://github.com/wp-cli/builds/tree/gh-pages/phar):

    cd wp-cli/builds/phar
    cp wp-cli-release.phar wp-cli.phar
    md5 -q wp-cli.phar > wp-cli.phar.md5
    shasum -a 512 wp-cli.phar | cut -d ' ' -f 1 > wp-cli.phar.sha512

- [ ] Sign the release with GPG (see <https://github.com/wp-cli/wp-cli/issues/2121>):

    gpg --output wp-cli.phar.gpg --default-key releases@wp-cli.org --sign wp-cli.phar
    gpg --output wp-cli.phar.asc --default-key releases@wp-cli.org --detach-sig --armor wp-cli.phar

    > Note: The GPG key for `releases@wp-cli.org` has to be shared amongst maintainers.

- [ ] Perform one last sanity check on the Phar by ensuring it displays its information

    php wp-cli.phar --info

- [ ] Commit the Phar and its hashes to the builds repo

    git status
    git add .
    git commit -m "Update stable to v1.x.0"

- [ ] Create a release on Github: <https://github.com/wp-cli/wp-cli/releases>. Make sure to upload the Phar from the builds directory.

    cp wp-cli.phar wp-cli-1.x.0.phar
    cp wp-cli.phar.gpg wp-cli-1.x.0.phar.gpg
    cp wp-cli.phar.asc wp-cli-1.x.0.phar.asc
    cp wp-cli.phar.md5 wp-cli-1.x.0.phar.md5
    cp wp-cli.phar.sha512 wp-cli-1.x.0.phar.sha512

- [ ] Verify Phar release artifact

    $ wp cli update
    You have version 1.4.0-alpha-88450b8. Would you like to update to 1.4.0? [y/n] y
    Downloading from https://github.com/wp-cli/wp-cli/releases/download/v1.4.0/wp-cli-1.4.0.phar...
    md5 hash verified: 179fc8dacbfe3ebc2d00ba57a333c982
    New version works. Proceeding to replace.
    Success: Updated WP-CLI to 1.4.0.
    $ wp @daniel option get home
    https://danielbachhuber.com

### Updating the Debian and RPM builds

- [ ] Trigger Travis CI build on [wp-cli/deb-build](https://github.com/wp-cli/deb-build)
- [ ] Trigger Travis CI build on [wp-cli/rpm-build](https://github.com/wp-cli/rpm-build)

### Updating the Homebrew formula (should happen automatically)

- [ ] Update the url and sha256 here: https://github.com/Homebrew/homebrew-php/blob/master/Formula/wp-cli.rb#L8-L9

    wget https://github.com/wp-cli/wp-cli/archive/v1.x.0.tar.gz
    shasum -a 256 v1.x.0.tar.gz

- [ ] Make a commit with format "wp-cli 2.x.0"

### Updating the website

- [ ] Verify <https://github.com/wp-cli/wp-cli.github.com#readme> is up-to-date

- [ ] Update the [roadmap](https://make.wordpress.org/cli/handbook/roadmap/)

- [ ] Tag a release of the website

### Announcing

- [ ] Announce release on the [WP-CLI Twitter account](https://twitter.com/wpcli)
- [ ] Announce using the `/announce` slash command in the [`#cli`](https://wordpress.slack.com/messages/C02RP4T41) Slack room.

### Bumping WP-CLI version again

- [ ] Bump [VERSION](https://github.com/wp-cli/wp-cli/blob/master/VERSION) in [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli) again.

- [ ] Change the version constraint on `"wp-cli/wp-cli"` in `wp-cli/wp-cli-bundle`'s [`composer.json`](https://github.com/wp-cli/wp-cli-bundle/blob/master/composer.json) file back to `"dev-master"`.
