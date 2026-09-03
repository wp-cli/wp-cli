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

    Do not tag anything by hand — the `Prepare Release` workflow creates both tags later on.

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

- [ ] Optional: dry-run the [`Prepare Release`](https://github.com/wp-cli/wp-cli/actions/workflows/prepare-release.yml) workflow with **Dry run** ticked.

    This runs every validation — `VERSION` matches, the bundle locks the framework version, the release Phar was actually built for this version, neither tag exists yet — without pushing anything.

- [ ] Run the [`Prepare Release`](https://github.com/wp-cli/wp-cli/actions/workflows/prepare-release.yml) workflow with the version to release (e.g. `2.13.0`).

    It tags [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle/) first and then [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli/), which in turn triggers the release workflow. That workflow:
    - Re-checks that the release Phar matches the tag, and refuses to continue if it is stale.
    - Generates the contributor list and changelog (also uploaded as a workflow artifact).
    - Promotes the Phar and manifest in the `builds` repo to stable and generates the checksums.
    - Verifies its own checksums, and repoints `deb/php-wpcli_latest_all.deb`.
    - Pushes the updated stable build back to `wp-cli/builds`.
    - Creates draft releases with the changelog and 5 attached assets on both [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli/) and [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle/).

- [ ] Sign the release with GPG (see <https://github.com/wp-cli/wp-cli/issues/2121>).

    This is still done by hand, because the `releases@wp-cli.org` key is not available to GitHub Actions. Pull the stable Phar the workflow just pushed, sign it, and commit the signatures back:

    ```
    cd wp-cli/builds/phar
    git pull
    gpg --output wp-cli.phar.gpg --default-key releases@wp-cli.org --sign wp-cli.phar
    gpg --output wp-cli.phar.asc --default-key releases@wp-cli.org --detach-sig --armor wp-cli.phar
    gpg --verify wp-cli.phar.asc wp-cli.phar
    git commit -m "Sign stable v2.x.0" wp-cli.phar.gpg wp-cli.phar.asc
    git push
    ```

    Note: The GPG key for `releases@wp-cli.org` has to be shared amongst maintainers.

- [ ] Attach the signatures to both draft releases, so that they carry all 7 assets.

    ```
    cp wp-cli.phar.gpg wp-cli-2.x.0.phar.gpg
    cp wp-cli.phar.asc wp-cli-2.x.0.phar.asc
    gh release upload v2.x.0 wp-cli-2.x.0.phar.gpg wp-cli-2.x.0.phar.asc --repo wp-cli/wp-cli
    gh release upload v2.x.0 wp-cli-2.x.0.phar.gpg wp-cli-2.x.0.phar.asc --repo wp-cli/wp-cli-bundle
    ```

- [ ] Review and publish the draft releases:
    - Review draft on [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli/releases) and publish.
    - Review draft on [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle/releases) and publish.

    Publishing the release on `wp-cli/wp-cli` automatically triggers post-release automation, which first verifies the published artifacts (checksums, that the Phar runs and reports the right version, and that it is byte-identical to the stable build in `wp-cli/builds`). Only if that passes does it:
    - Bump the version to the next alpha in [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli) (`VERSION` file and `composer.json` branch alias).
    - Reset the framework dependency in [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle) back to `"dev-main"`.
    - Close the shipped milestones across all bundled repositories.

    Handbook regeneration runs separately, from [`trigger-handbook-regeneration.yml`](https://github.com/wp-cli/wp-cli/blob/main/.github/workflows/trigger-handbook-regeneration.yml).

- [ ] Spot-check the upgrade path end to end

    The checksums and the Phar itself are verified automatically; this covers the parts that automation cannot reach.

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

- [ ] Verify the Homebrew formulae were bumped.

    This happens on its own. Homebrew autobumps every `homebrew-core` formula that has not opted out via `no_autobump!` or a `livecheck ... skip`, and neither [`wp-cli`](https://github.com/Homebrew/homebrew-core/blob/master/Formula/w/wp-cli.rb) nor [`wp-cli-completion`](https://github.com/Homebrew/homebrew-core/blob/master/Formula/w/wp-cli-completion.rb) does. BrewTestBot polls every 3 hours, so expect the bump PRs to show up **a few hours after the release is published** — there is nothing to do but confirm they landed.

    If nothing has appeared by the next day, check [BrewTestBot's pull requests](https://github.com/Homebrew/homebrew-core/pulls?q=is%3Apr+author%3Aapp%2Fbrewtestbot+wp-cli) and only then open one by hand:

    ```
    brew bump-formula-pr --strict wp-cli --url=https://github.com/wp-cli/wp-cli/releases/download/v2.x.x/wp-cli-2.x.x.phar --sha256=$(wget -qO- https://github.com/wp-cli/wp-cli/releases/download/v2.x.x/wp-cli-2.x.x.phar | sha256sum | cut -d " " -f 1)
    ```

    Note that `wp-cli-completion` tracks the Git tag tarball rather than the Phar, so it needs its own bump with a different `--url`.

- [ ] Publish the release blog post on the [make.wordpress.org CLI blog](https://make.wordpress.org/cli/).

- [ ] Announce release on Twitter / Slack.
