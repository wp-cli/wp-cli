WP-CLI
========

[![Build Status](https://travis-ci.org/wp-cli/wp-cli.png?branch=master)](https://travis-ci.org/wp-cli/wp-cli)

wp-cli is a set of command-line tools for managing WordPress installations.

Where can I get more info?
--------------------------
For documentation, usage, and examples, check out [wp-cli.org](http://wp-cli.org/).

I'm running into troubles, what can I do?
-----------------------------------------

To suggest a feature, report a bug, or general discussion, visit the [issues section](https://github.com/wp-cli/wp-cli/issues).

Who's behind this thing?
------------------------

We are [Andreas Creten](https://github.com/andreascreten) and [Cristi Burcă](https://github.com/scribu), friendly guys from Europe.

A complete list of contributors can be found [here](https://github.com/wp-cli/wp-cli/contributors).

Need even more info?
--------------------
Read our [wiki](https://github.com/wp-cli/wp-cli/wiki) and find out how to create your own commands with our [commands cookbook](https://github.com/wp-cli/wp-cli/wiki/Commands-Cookbook).

If you want to receive an email for every single commit, you can subscribe to the [wp-cli-commits](https://groups.google.com/forum/?fromgroups=#!forum/wp-cli-commits) mailing list.

Running tests
-------------

There are two types of tests:

* unit tests, implemented using [PHPUnit](http://phpunit.de/)
* functional tests, implemented using [Behat](http://behat.org)

All the test dependencies can be installed using [composer](http://getcomposer.org/):

    composer.phar install --dev

Before running the tests, you'll need a MySQL user called `wp_cli_test` with the
password `password1` that has full privileges on the MySQL database `wp_cli_test`.
Running the following as root in MySQL should do the trick:

    GRANT ALL PRIVILEGES ON wp_cli_test.* TO "wp_cli_test"@"localhost" IDENTIFIED BY "password1";

Finally, to run the unit tests:

    vendor/bin/phpunit

And to run the functional tests:

    vendor/bin/behat
