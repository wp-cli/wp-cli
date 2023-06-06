---
name: "\U0001F527 Patch Release Checklist"
about: "\U0001F512 Maintainers only: create a checklist for a patch release process"
title: 'Release checklist for v2.x.x'
labels: 'i: scope:distribution'
assignees: 'schlessera'

---
# Patch Release Checklist - v2.x.x

### Preparation

- [ ] Write release post on the [Make.org CLI blog](https://make.wordpress.org/cli/wp-admin/post-new.php)
- [ ] Regenerate command and internal API docs

    Command and internal API docs need to be regenerated before every major release, because they're meant to correlate with the release.

    ```
    git clone git@github.com:wp-cli/handbook.git
    cd handbook
    wp handbook gen-all
    ```

- [ ] Verify results of [automated test suite](https://github.com/wp-cli/automated-tests)

### Updating WP-CLI

- [ ] Create a new release branch from the last tagged patch release

    ```
    $ git checkout v1.4.0
    Note: checking out 'v1.4.0'
    You are in 'detached HEAD' state. You can look around, make experimental
    changes and commit them, and you can discard any commits you make in this
    state without impacting any branches by performing another checkout.
    $ git checkout -b release-1-4-1
    Switched to a new branch 'release-1-4-1'
    ```

- [ ] Cherry-pick existing commits and package versions to the new release branch.

    Because patch releases should just be used for bug fixes, you should first fix the bug on master, and then cherry-pick the fix to the release branch. It's up to your discretion as to whether you cherry-pick the commits directly to the release branch *or* create a feature branch and pull request against the release branch.

    If the bug existed in a package, you'll need to create a point release above the last bundled version for the package and update `composer.lock` to load that point release.

- [ ] Ensure that the contents of [VERSION](https://github.com/wp-cli/wp-cli/blob/master/VERSION) in `wp-cli/wp-cli` are changed to latest.

- [ ] Update the WP-CLI version mention in `wp-cli/wp-cli`'s `README.md` ([ref](https://github.com/wp-cli/wp-cli/issues/3647)).

- [ ] Lock `php-cli-tools` version (if needed)

    `php-cli-tools` is sometimes set to `dev-master` during the development cycle. During the WP-CLI release process, `composer.json` should be locked to a specific version. `php-cli-tools` may need a new version tagged as well.

- [ ] Lock the framework version in the ([bundle repository](https://github.com/wp-cli/wp-cli-bundle/))

    The version constraint of the `wp-cli/wp-cli` framework requirement is usually set to `"dev-master"`. Set it to the stable tagged release that represents the version to be published.

    As an example, if releasing version 2.1.0 of WP-CLI, the `wp-cli/wp-cli-bundle` should require `"wp-cli/wp-cli": "^2.1.0"`.

### Updating the contributor list

- [ ] Fetch the list of contributors (from within the [`wp-cli/wp-cli-dev`](https://githubcom/wp-cli/wp-cli-dev/) project repo)

    From within the `wp-cli/wp-cli-dev` project repo, use `wp maintenance contrib-list` to generate a list of release contributors:

    ```
    GITHUB_TOKEN=<token> wp maintenance contrib-list --format=markdown
    ```

    This script identifies pull request creators from `wp-cli/wp-cli-bundle`, `wp-cli/wp-cli`, `wp-cli/handbook`, and all bundled WP-CLI commands (e.g. `wp-cli/*-command`).

    For `wp-cli/wp-cli-bundle`, `wp-cli/wp-cli` and `wp-cli/handbook`, the script uses the currently open release milestone.

    For all bundled WP-CLI commands, the script uses all closed milestones since the last WP-CLI release (as identified by the version present in the `composer.lock` file). If a command was newly bundled since last release, contributors to that command will need to be manually added to the list.

    The script will also produce a total contributor and pull request count you can use in the release post.

### Updating the Phar build

- [ ] Create a PR from the `release-x-x-x` branch in `wp-cli/wp-cli-bundle` and merge it. This will trigger the `wp-cli-release.*` builds.

- [ ] Create a git tag and push it.

- [ ] Create a stable [Phar build](https://github.com/wp-cli/builds/tree/gh-pages/phar):

    ```
    cd wp-cli/builds/phar
    cp wp-cli-release.phar wp-cli.phar
    md5 -q wp-cli.phar > wp-cli.phar.md5
    shasum -a 512 wp-cli.phar | cut -d ' ' -f 1 > wp-cli.phar.sha512
    ```

- [ ] Sign the release with GPG (see <https://github.com/wp-cli/wp-cli/issues/2121>):

    ```
    gpg --output wp-cli.phar.gpg --default-key releases@wp-cli.org --sign wp-cli.phar
    gpg --output wp-cli.phar.asc --default-key releases@wp-cli.org --detach-sig --armor wp-cli.phar
    ```

    Note: The GPG key for `releases@wp-cli.org` has to be shared amongst maintainers.

- [ ] Perform one last sanity check on the Phar by ensuring it displays its information

    ```
    php wp-cli.phar --info
    ```

- [ ] Commit the Phar and its hashes to the builds repo

    ```
    git status
    git add .
    git commit -m "Update stable to v1.x.0"
    ```

- [ ] Create a release on Github: <https://github.com/wp-cli/wp-cli/releases>. Make sure to upload the Phar from the builds directory.

    ```
    cp wp-cli.phar wp-cli-1.x.0.phar
    cp wp-cli.phar.gpg wp-cli-1.x.0.phar.gpg
    cp wp-cli.phar.asc wp-cli-1.x.0.phar.asc
    cp wp-cli.phar.md5 wp-cli-1.x.0.phar.md5
    cp wp-cli.phar.sha512 wp-cli-1.x.0.phar.sha512
    ```

- [ ] Verify Phar release artifact

    ```
    $ wp cli update
    You have version 1.4.0-alpha-88450b8. Would you like to update to 1.4.0? [y/n] y
    Downloading from https://github.com/wp-cli/wp-cli/releases/download/v1.4.0/wp-cli-1.4.0.phar...
    md5 hash verified: 179fc8dacbfe3ebc2d00ba57a333c982
    New version works. Proceeding to replace.
    Success: Updated WP-CLI to 1.4.0.
    $ wp @daniel option get home
    https://danielbachhuber.com
    ```

### Updating the Debian and RPM builds

- [ ] Trigger Travis CI build on [wp-cli/deb-build](https://github.com/wp-cli/deb-build)
- [ ] Trigger Travis CI build on [wp-cli/rpm-build](https://github.com/wp-cli/rpm-build)

    The two builds shouldn't be triggered at the same time, as one of them will then fail to push its build artifact due to the remote not being in the same state anymore.

    Due to aggressive caching by the GitHub servers, the scripts might pull in cached version of the previous release instead of the new one. This seems to resolve automatically in a period of 24 hours.

### Updating the Homebrew formula (should happen automatically)

- [ ] Update the url and sha256 here: https://github.com/Homebrew/homebrew-core/blob/master/Formula/wp-cli.rb#L4-L5

    The easiest way to do so is by using the following command:

    ```
    brew bump-formula-pr --strict wp-cli --url=https://github.com/wp-cli/wp-cli/releases/download/v2.x.x/wp-cli-2.x.x.phar --sha256=$(wget -qO- https://github.com/wp-cli/wp-cli/releases/download/v2.x.x/wp-cli-2.x.x.phar - | sha256sum | cut -d " " -f 1)
    ```

### Updating the website

- [ ] Verify <https://github.com/wp-cli/wp-cli.github.com#readme> is up-to-date

- [ ] Update the [roadmap](https://make.wordpress.org/cli/handbook/roadmap/)

- [ ] Update all version references on the homepage (and localized homepages).

- [ ] Tag a release of the website

### Announcing

- [ ] Announce release on the [WP-CLI Twitter account](https://twitter.com/wpcli)
- [ ] Announce using the `/announce` slash command in the [`#cli`](https://wordpress.slack.com/messages/C02RP4T41) Slack room.

### Bumping WP-CLI version again

- [ ] Bump [VERSION](https://github.com/wp-cli/wp-cli/blob/master/VERSION) in [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli) again.

    For instance, if the release version was `0.24.0`, the version should be bumped to `0.25.0-alpha`. Doing so ensure `wp cli update --nightly` works as expected.

- [ ] Change the version constraint on `"wp-cli/wp-cli"` in `wp-cli/wp-cli-bundle`'s [`composer.json`](https://github.com/wp-cli/wp-cli-bundle/blob/master/composer.json) file back to `"dev-master"`.
