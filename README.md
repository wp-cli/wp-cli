WP-CLI: Wordpress Command Line Tools
============================

What is wp-cli
--------------

A command line tool to do maintenance work on a Wordpress install from the command line.

Installing
----------

Installing wp-cli is extremely simple: place the wp-cli folder in your Wordpress root, (on the same level as wp-admin and wp-content).
That's it!

Usage
-----

In your terminal, go into the wp-cli folder.

Type the following command:
`./wp help`

This will show you an output similar to this:
`Example usage:
    wp google-sitemap [build|help] ...
    wp core [update|help] ...
    wp home [help] ...
    wp plugins [status|activate|deactivate|install|delete|update|help] ...`

Adding commands
---------------

Adding commands to wp-cli is very easy. You can even add them from within your own plugin.

Requirements
------------

 * PHP >= 5.3