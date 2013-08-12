Contribute
==========

So you've got an awesome idea to throw into WP-CLI. Great! Here's the process, in a nutshell:

1. [Fork](https://github.com/wp-cli/wp-cli/fork) the repository.
2. Make the code changes in your fork.
3. Open a pull request.

It doesn't matter if the code isn't perfect. The idea is to get feedback early and iterate.

If you're adding a new feature, please add one or more functional tests for it in the `features/` directory. See below.

Lastly, please follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

Running and writing tests
-------------------------

There are two types of automated tests:

* unit tests, implemented using [PHPUnit](http://phpunit.de/)
* functional tests, implemented using [Behat](http://behat.org)

To set everything up, just run:

```bash
./utils/dev-build
```

### Unit tests

The unit test files are in the `tests/` directory.

To run the unit tests, just execute:

    php phpunit.phar

### Functional tests

The functional test files are in the `features/` directory.

Before running the functional tests, you'll need a MySQL user called `wp_cli_test` with the
password `password1` that has full privileges on the MySQL database `wp_cli_test`.
Running the following as root in MySQL should do the trick:

    GRANT ALL PRIVILEGES ON wp_cli_test.* TO "wp_cli_test"@"localhost" IDENTIFIED BY "password1";

Then, to run the entire test suite:

    php behat.phar --expand

Or to test a single feature:

    php behat.phar features/core.feature

More info can be found from `php behat.phar --help`.

Finally...
----------

Thanks! Hacking on WP-CLI should be fun. If you find any of this hard to figure
out, let us know so we can improve our process or documentation!
