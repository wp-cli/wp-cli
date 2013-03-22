Contribute
==========

So you've got an awesome idea to throw into WP-CLI. Great! Please keep the following in mind:

* The best way to get feedback is by opening an issue or a pull request; if you think the code shouldn't be merged yet, just say so.
* If you're adding a new command or subcommand, please consider adding a functional test for it in the `features` directory. Also, please create the appropriate `.txt` file in the `man-src` directory.
* Please follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

Generating man pages
--------------------

To generate a man page, WP-CLI looks for `.txt` files in the `man-src` directory. It also gathers information from the inline comments and the `@synopsis` annotations.

The compiled man page is placed in the `man` directory.

To (re)generate one or more man pages, you first need to have the [ronn](https://rubygems.org/gems/ronn) ruby gem installed.

Then, you can run one of the following:

* `wp --man` - regenerates all man pages
* `wp core --man` - regenerates man pages for the `core` command
* `wp core download --man` - regenerates man page only for the `core download` subcommand

Running the tests
-----------------

There are two types of tests:

* unit tests, implemented using [PHPUnit](http://phpunit.de/)
* functional tests, implemented using [Behat](http://behat.org)

All the test dependencies can be installed using [Composer](http://getcomposer.org/):

    php composer.phar install --dev

Before running the tests, you'll need a MySQL user called `wp_cli_test` with the
password `password1` that has full privileges on the MySQL database `wp_cli_test`.
Running the following as root in MySQL should do the trick:

    GRANT ALL PRIVILEGES ON wp_cli_test.* TO "wp_cli_test"@"localhost" IDENTIFIED BY "password1";

Finally, to run the tests:

    vendor/bin/phpunit
    vendor/bin/behat

Finally...
----------

Thanks! Hacking on WP-CLI should be fun. If you find any of this hard to figure
out, let us know so we can improve our process or documentation!
