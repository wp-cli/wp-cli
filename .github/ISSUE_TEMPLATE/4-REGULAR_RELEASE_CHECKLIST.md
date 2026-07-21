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

### Updating the Phar build & Publishing GitHub Releases

- [ ] Create a PR from the `release-x-x-x` branch in `wp-cli/wp-cli-bundle` and merge it. This will trigger the `wp-cli-release.*` builds.

- [ ] Push the Git tag `v2.x.0` to [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli/).

    This automatically triggers the release workflow in `wp-cli/wp-cli` which:
    - Generates the contributor list and changelog.
    - Promotes the Phar and manifest in the `builds` repo to stable, generates checksums, and signs with GPG.
    - Pushes the updated stable build back to `wp-cli/builds`.
    - Creates draft releases with the changelog and all 7 attached assets on both [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli/) and [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle/).

- [ ] Review and publish the draft releases:
    - Review draft on [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli/releases) and publish.
    - Review draft on [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle/releases) and publish.

    Publishing the release on `wp-cli/wp-cli` automatically triggers post-release automation workflows which:
    - Bump the version to the next alpha in [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli) (`VERSION` file and `composer.json` branch alias).
    - Reset the framework dependency in [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle) back to `"dev-main"`.
    - Close the shipped milestones across all bundled repositories.
    - Trigger handbook docs regeneration and website repository dispatch.

- [ ] Verify Phar release artifact

    ```
    $ wp cli update
    You are currently using WP-CLI version 2.12.0-alpha-d2bfea9. Would you like to update to 2.12.0? [y/n] y
    Downloading from https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar...
    sha512 hash verified: fe19025cc113142492a3ca68dd93d20ba4164e5ecb3c0a0d86a9db7e06b917201120763fa2b8256addeaa9cb745b2b8bef8e8d74a697230e30ef681f13e09186
    New version works. Proceeding to replace.
    Success: Updated WP-CLI to 2.12.0.
    $ wp cli version
    WP-CLI 2.12.0
    $wp eval 'echo \WP_CLI\Utils\http_request( "GET", "https://api.wordpress.org/core/version-check/1.6/" )->body;' --skip-wordpress
    <PHP serialized string with version numbers>
    ```

### Post-Release Manual Tasks

- [ ] Update Homebrew formula (if not automated via action):
    Follow this [example PR](https://github.com/Homebrew/homebrew-core/pull/152339) to update version numbers and sha256 for both `wp-cli` and `wp-cli-completion`.

- [ ] Publish the release blog post on the [make.wordpress.org CLI blog](https://make.wordpress.org/cli/).

- [ ] Announce release on Twitter / Slack.
