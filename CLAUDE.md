# Working on wp-cli/wp-cli

Read @AGENTS.md first — it describes the conventions shared by every WP-CLI
package. This file covers only what is specific to this repository, and what
tends to go wrong here.

`AGENTS.md` is synced across the whole organization from `wp-cli/.github`, so
repository-specific notes belong in this file rather than there.

## Set this before running anything

```bash
export NO_COLOR=1
export WP_CLI_TEST_QUIET=1
```

`NO_COLOR` stops the test runners from forcing ANSI escape codes into output
you are going to read back. `WP_CLI_TEST_QUIET` switches PHP_CodeSniffer and
PHPStan to one-line-per-problem reporting, drops their progress tickers, and
drops Behat's step definition snippets. Both are handled by `wp-cli/wp-cli-tests`.

## Do not run `composer test`

`composer test` runs all five suites, and the Behat suite is 430 scenarios of
which 205 install WordPress from scratch. It takes tens of minutes, needs `jq`,
needs a MySQL or MariaDB client with a prepared test database, and needs network
access to WordPress.org. Running it to check a two-line change is not a good
trade.

Run the narrowest thing that covers the change instead:

| You changed | Run |
| --- | --- |
| Anything under `php/` | `composer phpcs -- <file>` and `composer phpstan` |
| Logic covered by unit tests | `composer phpunit -- --filter <TestName>` |
| A single feature file | `composer behat -- features/<name>.feature` |
| One scenario | `composer behat -- features/<name>.feature:<line>` |

Leave the full `composer test` to CI, which runs the suites in parallel across
a matrix. Do run the full suite locally when you have genuinely touched
something cross-cutting, such as `php/WP_CLI/Runner.php` or `php/utils.php`.

## Behat

* `composer prepare-tests` sets up the test database and only needs to be run
  once. It needs a database user that can create databases.
* With no `mysql` or `mariadb` client on `PATH`, the runner falls back to SQLite
  and says so in a warning. The suite still runs, but it is not what CI runs.
* `composer behat -- --stop-on-failure` stops at the first failure instead of
  working through the remaining scenarios.
* `composer behat-rerun` re-runs only the scenarios that failed last time. Use
  it after a fix rather than repeating the original invocation.
* Use `When I try` for anything expected to exit non-zero or write to STDERR,
  and `When I run` for everything else. A `When I run` step that hits either
  case fails with an explanation of this distinction.
* A failing step prints the command, its STDOUT, its STDERR, the working
  directory and the exit status. Read that block before re-running anything.

## Static analysis and coding style

* PHPStan runs at level 9 with no baseline. New errors have to be fixed rather
  than ignored. If an ignore is genuinely unavoidable, use a narrowly scoped
  `@phpstan-ignore` with a comment explaining why.
* `composer phpcbf` fixes most style violations automatically. It reports what
  it did through its exit code, so a non-zero exit is not by itself a failure —
  confirm the result by re-running `composer phpcs`.
* Everything under `bundle/` is vendored third-party code (the Requests
  library). It is excluded from PHP_CodeSniffer, PHPStan and the spellchecker.
  Do not edit it; changes belong upstream.

## A green `composer test` is not a green CI

Three checks run in CI that `composer test` does not cover:

* `actionlint` over `.github/workflows/`
* `gherkin-lint` over `features/*.feature` (its config lives in `wp-cli/.github`)
* `typos` over the tree, configured by `.typos.toml`

The spellchecker is the one that catches people out. `.typos.toml` excludes
`bundle/`, `tests/*.php` and `features/*.feature`, so it mainly polices `php/`
and the docs. Inline `// spellchecker:ignore-next-line` where a false positive
is unavoidable.

## Command documentation

Command documentation lives in the PHPDoc blocks above the command methods in
`php/commands/src/`. `WP_CLI\DocParser` reads those blocks at runtime: the
`## OPTIONS` section becomes the command's synopsis and its argument
validation, and annotations such as `@when` control when the command runs. A
change to a docblock is therefore a change to behavior, not just to prose.
