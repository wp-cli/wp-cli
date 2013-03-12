Contribute
==========

So you've got an awesome idea to throw into WP-CLI. Great! Please keep the
following in mind:

* If you're adding a new command or subcommand, please consider adding a functional test for it in the `features` directory. Also, please create the appropriate `.txt` file in the `man-src` directory.
* Please follow the [WordPress Coding Standards](http://make.wordpress.org/core/handbook/coding-standards/).

Test Dependencies
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
