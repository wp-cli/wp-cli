---
name: "\U0001F680 Regular Release Checklist"
about: "\U0001F512 Maintainers only: create a checklist for a regular release process"
title: 'Release checklist for v2.x.x'
labels: 'i: scope:distribution'
assignees: ''

---
# Regular Release Checklist - v2.x.x

### Preparation

- [ ] Mention on Slack that a release is being prepared

    People should wait with updating until the announcement. Before that, things are still in motion.

- [ ] Verify all tests pass in the [automated test suite](https://github.com/wp-cli/automated-tests)

- [ ] Regenerate command and internal API docs

    Command and internal API docs need to be regenerated before every major release, because they're meant to correlate with the release.

    ```
    git clone git@github.com:wp-cli/handbook.git
    cd handbook
    WP_CLI_PACKAGES_DIR=bin/packages ../wp-cli-bundle/vendor/bin/wp handbook gen-all
    ```

- [ ] Fetch the list of contributors (from within the [`wp-cli/wp-cli-dev`](https://githubcom/wp-cli/wp-cli-dev/) project repo)

    From within the `wp-cli/wp-cli-dev` project repo, use `wp maintenance contrib-list` to generate a list of release contributors:

    ```
    GITHUB_TOKEN=<token> wp maintenance contrib-list --format=markdown
    ```

    This script identifies pull request creators from `wp-cli/wp-cli-bundle`, `wp-cli/wp-cli`, `wp-cli/handbook`, and all bundled WP-CLI commands (e.g. `wp-cli/*-command`).

    For `wp-cli/wp-cli-bundle`, `wp-cli/wp-cli` and `wp-cli/handbook`, the script uses the currently open release milestone.

    For all bundled WP-CLI commands, the script uses all closed milestones since the last WP-CLI release (as identified by the version present in the `composer.lock` file). If a command was newly bundled since last release, contributors to that command will need to be manually added to the list.

    The script will also produce a total contributor and pull request count you can use in the release post.

- [ ] Generate release notes for all packages (from within the [`wp-cli/wp-cli-dev`](https://githubcom/wp-cli/wp-cli-dev/) project repo)

    From within the `wp-cli/wp-cli-dev` project repo, use `wp maintenance release-notes` to generate the release notes:

    ```
    GITHUB_TOKEN=<token> wp maintenance release-notes
    ```

- [ ] Draft release post on the [make.wordpress.org CLI blog](https://make.wordpress.org/cli/wp-admin/post-new.php)

    Use previous release blog posts as inspiration.
    
    Use the contributor list and changelog from the previous steps in the blog post.

    Note down the permalink already now, as it will be needed in later steps.

### Updating WP-CLI

#### In [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli/)

- [ ] Create a branch called `prepare-x-x-x` to prepare the version bump.

- [ ] Update the WP-CLI version mention in `wp-cli/wp-cli`'s `README.md` ([ref](https://github.com/wp-cli/wp-cli/issues/3647)).

- [ ] Lock `php-cli-tools` version (if needed)
    `php-cli-tools` is sometimes set to `dev-main` during the development cycle. During the WP-CLI release process, `composer.json` should be locked to a specific version. `php-cli-tools` may need a new version tagged as well.

- [ ] Ensure that the contents of [VERSION](https://github.com/wp-cli/wp-cli/blob/master/VERSION) in `wp-cli/wp-cli` are changed to latest.

- [ ] Submit the PR and merge it once all checks are green.

- [ ] Create a Git tag for the new version. **Do not create a GitHub _release_ just yet**. 

#### In [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle/)

- [ ] Create a branch called `release-x-x-x` to prepare the release PR. **Branch name is very important here!**

- [ ] Lock the framework version in `composer.json`

    The version constraint of the `wp-cli/wp-cli` framework requirement is usually set to `"dev-main"`. Set it to the stable tagged release that represents the version to be published.

    As an example, if releasing version 2.1.0 of WP-CLI, the `wp-cli/wp-cli-bundle` should require `"wp-cli/wp-cli": "^2.1.0"`.

    ```
    composer require wp-cli/wp-cli:^2.1.0
    ```

### Updating the Phar build

- [ ] Create a PR from the `release-x-x-x` branch in `wp-cli/wp-cli-bundle` and merge it. This will trigger the `wp-cli-release.*` builds.

- [ ] Create a Git tag and push it. **Do not create a GitHub _release_ just yet**.

- [ ] Create a stable [Phar build](https://github.com/wp-cli/builds/tree/gh-pages/phar):

    ```
    cd wp-cli/builds/phar
    cp wp-cli-release.phar wp-cli.phar
    md5 -q wp-cli.phar > wp-cli.phar.md5
    shasum -a 256 wp-cli.phar | cut -d ' ' -f 1 > wp-cli.phar.sha256
    shasum -a 512 wp-cli.phar | cut -d ' ' -f 1 > wp-cli.phar.sha512
    ```

- [ ] Sign the release with GPG (see <https://github.com/wp-cli/wp-cli/issues/2121>):

    ```
    gpg --output wp-cli.phar.gpg --default-key releases@wp-cli.org --sign wp-cli.phar
    gpg --output wp-cli.phar.asc --default-key releases@wp-cli.org --detach-sig --armor wp-cli.phar
    ```

    Note: The GPG key for `releases@wp-cli.org` has to be shared amongst maintainers.

- [ ] Verify the signature with `gpg --verify wp-cli.phar.asc wp-cli.phar`

- [ ] Perform one last sanity check on the Phar by ensuring it displays its information

    ```
    php wp-cli.phar --info
    ```

- [ ] Commit the Phar and its hashes to the `builds` repo

    ```
    git status
    git add .
    git commit -m "Update stable to v2.x.0"
    ```

- [ ] Create actual releases on GitHub: Make sure to upload the previously generated Phar from the `builds` repo.

    ```
    cp wp-cli.phar wp-cli-2.x.0.phar
    cp wp-cli.phar.gpg wp-cli-2.x.0.phar.gpg
    cp wp-cli.phar.asc wp-cli-2.x.0.phar.asc
    cp wp-cli.phar.md5 wp-cli-2.x.0.phar.md5
    cp wp-cli.phar.sha512 wp-cli-2.x.0.phar.sha256
    cp wp-cli.phar.sha512 wp-cli-2.x.0.phar.sha512
    ```

    Do this for both [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli/) and [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle/)

- [ ] Verify Phar release artifact

    ```
    $ wp cli update
    You have version 1.4.0-alpha-88450b8. Would you like to update to 1.4.0? [y/n] y
    Downloading from https://github.com/wp-cli/wp-cli/releases/download/v1.4.0/wp-cli-1.4.0.phar...
    md5 hash verified: 179fc8dacbfe3ebc2d00ba57a333c982
    New version works. Proceeding to replace.
    Success: Updated WP-CLI to 1.4.0.
    $ wp cli version
    WP-CLI 2.8.1
    $wp eval 'echo \WP_CLI\Utils\http_request( "GET", "https://api.wordpress.org/core/version-check/1.6/" )->body;' --skip-wordpress
    <PHP serialized string with version numbers>
    ```

### Verify the Debian and RPM builds

- [ ] In the [`wp-cli/builds`](https://github.com/wp-cli/builds) repository, verify that the Debian and RPM builds exist

    **Note:** Right now, they are actually already generated automatically before all the tagging happened.

- [ ] Change symlink of `deb/php-wpcli_latest_all.deb` to point to the new stable version.

### Updating the Homebrew formula (should happen automatically)

- [ ] Follow this [example PR](https://github.com/Homebrew/homebrew-core/pull/152339) to update version numbers and sha256 for both `wp-cli` and `wp-cli-completion`

### Updating the website

- [ ] Verify <https://github.com/wp-cli/wp-cli.github.com#readme> is up-to-date

- [ ] Update all version references on the homepage (and localized homepages).

    Can be mostly done by using search and replace for the version number and the blog post URL.

- [ ] Update the [roadmap](https://make.wordpress.org/cli/handbook/roadmap/) to mention the current stable version

- [ ] Tag a release of the website

### Announcing

- [ ] Publish the blog post

- [ ] Announce release on the [WP-CLI Twitter account](https://twitter.com/wpcli)

- [ ] Optional: Announce using the `/announce` slash command in the [`#cli`](https://wordpress.slack.com/messages/C02RP4T41) Slack room.

    This pings a lot of people, so it's not always desired. Plus, the blog post will pop up on Slack anyway.

### Bumping WP-CLI version again

- [ ] Bump [VERSION](https://github.com/wp-cli/wp-cli/blob/master/VERSION) in [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli) again.

    For instance, if the release version was `2.8.0`, the version should be bumped to `2.9.0-alpha`. 

    Doing so ensures `wp cli update --nightly` works as expected.

- [ ] Change the version constraint on `"wp-cli/wp-cli"` in `wp-cli/wp-cli-bundle`'s [`composer.json`](https://github.com/wp-cli/wp-cli-bundle/blob/master/composer.json) file back to `"dev-main"`.

    ```
    composer require wp-cli/wp-cli:dev-main
    ```

- [ ] Adapt the branch alias in `wp-cli/wp-cli`'s [`composer.json`](https://github.com/wp-cli/wp-cli/blob/master/composer.json) file to match the new alpha version.
