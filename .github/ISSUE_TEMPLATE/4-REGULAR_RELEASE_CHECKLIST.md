---
name: "\U0001F680 Regular Release Checklist"
about: "\U0001F512 Maintainers only: create a checklist for a regular release process"
title: 'Release checklist for v3.x.x'
labels: 'i: scope:distribution'
assignees: ''

---
# Regular Release Checklist - v3.x.x

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

    Do not tag anything by hand — the `Prepare Release` workflow creates both tags later on, and the framework tag goes on exactly this merged commit (it is what the bundle locks next).

#### In [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle/)

- [ ] Create a branch called `release-x-x-x` to prepare the release PR. **Branch name is very important here!**

- [ ] Lock the framework version in `composer.json`

    The version constraint of the `wp-cli/wp-cli` framework requirement is usually set to `"dev-main"`. Set it to the release being published. The framework tag does not exist yet at this point; the framework's `X.x-dev` branch alias is what lets Composer resolve the constraint to `main`.

    As an example, if releasing version 3.1.0 of WP-CLI, the `wp-cli/wp-cli-bundle` should require `"wp-cli/wp-cli": "^3.1"`.

    ```
    composer require wp-cli/wp-cli:^3.1
    ```

    Check that `composer.lock` now references the version bump commit that was just merged into `wp-cli/wp-cli` `main` (`source.reference` of `wp-cli/wp-cli`). That commit is what the `Prepare Release` workflow will tag, and its `VERSION` file has to match.

- [ ] Push the `release-x-x-x` branch. **The push is what builds the release Phar**: the bundle `Deployment` workflow builds `wp-cli-release.*` for pushes to `release-**` branches (merges to `main` only build the nightly).

- [ ] Open the PR from the `release-x-x-x` branch and merge it.

    `Prepare Release` checks that `main` has the same tree as the commit the release Phar was built from. If anything else lands on `main` in between, rebase and push the release branch again so the Phar gets rebuilt, then merge.

### Tagging & Drafting the GitHub Releases

Nothing in this section changes what users get: the stable build in `wp-cli/builds` is only promoted after the release has been signed and published, see below.

- [ ] Optional: dry-run the [`Prepare Release`](https://github.com/wp-cli/wp-cli/actions/workflows/prepare-release.yml) workflow with **Dry run** ticked.

    This runs every validation — `VERSION` matches, the bundle's `composer.lock` locks the framework at a commit on `main` carrying that version, the release Phar was built from the bundle commit that is about to be tagged and reports the right version, neither tag exists yet — without pushing anything, and then dispatches a dry run of the `Release` workflow.

- [ ] Run the [`Prepare Release`](https://github.com/wp-cli/wp-cli/actions/workflows/prepare-release.yml) workflow with the version to release (e.g. `3.1.0`).

    Only `X.Y.0` versions are accepted; patch releases follow the patch release checklist. The `tag` job runs in the `release` environment, so it may wait for approval.

    It tags [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle/) at `main` and [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli/) at the commit the bundle locked, then dispatches the [`Release`](https://github.com/wp-cli/wp-cli/actions/workflows/release.yml) workflow from `main`. If the run fails after only one of the tags was pushed, simply re-run it: an existing tag that already points at the release commit is accepted and only the missing one gets pushed. Release tags are never moved. The `Release` workflow then:
    - Waits for the bundle deployment for this version to finish (the Debian package is built last, so `deb/php-wpcli_${VERSION}_all.deb` showing up in `wp-cli/builds` is the signal) and fails if it never does.
    - Re-checks that the tag is on `main`, that the release Phar was built from the tagged bundle commit and that it reports the right version, and refuses to continue otherwise.
    - Generates the contributor list and changelog (also uploaded as a workflow artifact).
    - Generates the checksums.
    - Creates draft releases with the changelog and 5 attached assets on both [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli/) and [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle/).

    Re-running it is safe: an existing draft carrying the same Phar is left alone (signatures and edited notes included), a published release is never touched, and a draft that already carries signatures fails the run if the Phar bytes changed.

### Signing & Publishing

- [ ] Sign the release with GPG (see <https://github.com/wp-cli/wp-cli/issues/2121>).

    This is done by hand: the `releases@wp-cli.org` key is deliberately not available to GitHub Actions. Download the Phar from the draft release, check it against the checksum, sign it, and attach the signatures to **both** draft releases so they carry all 7 assets:

    ```
    gh release download v3.x.0 --repo wp-cli/wp-cli --pattern 'wp-cli-3.x.0.phar*'
    echo "$(cat wp-cli-3.x.0.phar.sha512)  wp-cli-3.x.0.phar" | sha512sum --check
    gpg --output wp-cli-3.x.0.phar.gpg --default-key releases@wp-cli.org --sign wp-cli-3.x.0.phar
    gpg --output wp-cli-3.x.0.phar.asc --default-key releases@wp-cli.org --detach-sig --armor wp-cli-3.x.0.phar
    gpg --verify wp-cli-3.x.0.phar.asc wp-cli-3.x.0.phar
    gh release upload v3.x.0 wp-cli-3.x.0.phar.gpg wp-cli-3.x.0.phar.asc --repo wp-cli/wp-cli
    gh release upload v3.x.0 wp-cli-3.x.0.phar.gpg wp-cli-3.x.0.phar.asc --repo wp-cli/wp-cli-bundle
    ```

    Note: The GPG key for `releases@wp-cli.org` has to be shared amongst maintainers. Its public key is committed as [`.github/release-signing-key.asc`](https://github.com/wp-cli/wp-cli/blob/main/.github/release-signing-key.asc), and post-release automation refuses anything not signed with it.

- [ ] Review and publish the draft releases:
    - Review draft on [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli/releases) and publish.
    - Review draft on [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle/releases) and publish.

    Publishing the release on `wp-cli/wp-cli` triggers the [`Post-Release Automation`](https://github.com/wp-cli/wp-cli/actions/workflows/post-release.yml) workflow, which first verifies the published artifacts: all 7 assets are present, the checksums match, both signatures were made with the `releases@wp-cli.org` key and cover the published Phar, and only then that the Phar runs and reports the right version. Only if that passes does it:
    - Promote the published Phar, manifest, checksums and signatures to stable in [`wp-cli/builds`](https://github.com/wp-cli/builds) and repoint `deb/php-wpcli_latest_all.deb`. This is the moment `wp cli update`, the website and apt start serving the new version.
    - Open a PR against [`wp-cli/wp-cli`](https://github.com/wp-cli/wp-cli) bumping `VERSION` to the next alpha.
    - Open a PR against [`wp-cli/wp-cli-bundle`](https://github.com/wp-cli/wp-cli-bundle) resetting the framework dependency back to `"dev-main"`.
    - Close the shipped milestones across all bundled repositories (plus `wp-cli/wp-cli`, `wp-cli/wp-cli-bundle` and `wp-cli/package-command`).

    The `promote-stable`, `open-pull-requests` and `close-milestones` jobs run in the `release` environment, so they may wait for approval. Re-running the workflow is safe: a stable build that already matches is left alone, and existing post-release PRs are updated rather than duplicated.

    The Composer branch alias is handled separately by the org-wide `Check Branch Alias` workflow, which moves it to `X.x-dev` after a major release. Handbook regeneration runs separately as well, from [`trigger-handbook-regeneration.yml`](https://github.com/wp-cli/wp-cli/blob/main/.github/workflows/trigger-handbook-regeneration.yml).

- [ ] Approve and merge the two post-release PRs (version bump in `wp-cli/wp-cli`, framework reset in `wp-cli/wp-cli-bundle`) once their checks are green.

- [ ] Spot-check the upgrade path end to end

    The checksums, signatures and the Phar itself are verified automatically; this covers the parts that automation cannot reach.

    ```
    $ wp cli update
    You are currently using WP-CLI version 3.0.0-alpha-d2bfea9. Would you like to update to 3.0.0? [y/n] y
    Downloading from https://github.com/wp-cli/wp-cli/releases/download/v3.0.0/wp-cli-3.0.0.phar...
    sha512 hash verified: fe19025cc113142492a3ca68dd93d20ba4164e5ecb3c0a0d86a9db7e06b917201120763fa2b8256addeaa9cb745b2b8bef8e8d74a697230e30ef681f13e09186
    New version works. Proceeding to replace.
    Success: Updated WP-CLI to 3.0.0.
    $ wp cli version
    WP-CLI 3.0.0
    $wp eval 'echo \WP_CLI\Utils\http_request( "GET", "https://api.wordpress.org/core/version-check/1.6/" )->body;' --skip-wordpress
    <PHP serialized string with version numbers>
    ```

### Post-Release Manual Tasks

- [ ] Verify the Homebrew formulae were bumped.

    This happens on its own. Homebrew autobumps every `homebrew-core` formula that has not opted out via `no_autobump!` or a `livecheck ... skip`, and neither [`wp-cli`](https://github.com/Homebrew/homebrew-core/blob/master/Formula/w/wp-cli.rb) nor [`wp-cli-completion`](https://github.com/Homebrew/homebrew-core/blob/master/Formula/w/wp-cli-completion.rb) does. BrewTestBot polls every 3 hours, so expect the bump PRs to show up **a few hours after the release is published** — there is nothing to do but confirm they landed.

    If nothing has appeared by the next day, check [BrewTestBot's pull requests](https://github.com/Homebrew/homebrew-core/pulls?q=is%3Apr+author%3Aapp%2Fbrewtestbot+wp-cli) and only then open one by hand:

    ```
    brew bump-formula-pr --strict wp-cli --url=https://github.com/wp-cli/wp-cli/releases/download/v3.x.x/wp-cli-3.x.x.phar --sha256=$(wget -qO- https://github.com/wp-cli/wp-cli/releases/download/v3.x.x/wp-cli-3.x.x.phar | sha256sum | cut -d " " -f 1)
    ```

    Note that `wp-cli-completion` tracks the Git tag tarball rather than the Phar, so it needs its own bump with a different `--url`.

- [ ] Publish the release blog post on the [make.wordpress.org CLI blog](https://make.wordpress.org/cli/).

- [ ] Announce release on Twitter / Slack.
