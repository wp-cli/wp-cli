# WP-CLI

Command-line tools for managing WordPress installations.

[![Build Status](https://travis-ci.org/wp-cli/wp-cli.png?branch=master)](https://travis-ci.org/wp-cli/wp-cli)

[Documentation](http://wp-cli.org/)

Quick links: [Using](#using) | [Installing](#installing) | [Support](#support) | [Extending](#extending) | [Contributing](#contributing)

## Using

This project aims to provide command-line support for any action you can perform in the WordPress admin and many you can't. So you can [install a plugin](http://wp-cli.org/commands/plugin/install/):

`
$ wp plugin install rest-api
`

And [activate it](http://wp-cli.org/commands/plugin/activate/):

`
$ wp plugin activate rest-api
`

View the [Quick Start](http://wp-cli.org/docs/quick-start/) guide or jump to the [complete list of commands](http://wp-cli.org/commands/) for managing themes and plugins, importing and exporting data, performing database search-replace operations and more.

## Installing

The recommended way to install WP-CLI is to download the Phar build, mark it executable and place it in your PATH.

Download the `[wp-cli.phar](https://raw.github.com/wp-cli/builds/gh-pages/phar/wp-cli.phar)` using `wget` or `curl`. For example:

`$ curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar`

Check if it is working:

`$ php wp-cli.phar --info`

To use it from the command line by typing `wp`, make the file executable and move it to somewhere in your PATH. For example:

`
$ chmod +x wp-cli.phar
$ sudo mv wp-cli.phar /usr/local/bin/wp
`

If WP-CLI installed successfully, you should see something like this when you run `wp --info`:

`
$ wp --info
PHP binary:    /usr/bin/php5
PHP version:    5.5.9-1ubuntu4.14
php.ini used:   /etc/php5/cli/php.ini
WP-CLI root dir:        /home/wp-cli/.wp-cli
WP-CLI packages dir:    /home/wp-cli/.wp-cli/packages/
WP-CLI global config:   /home/wp-cli/.wp-cli/config.yml
WP-CLI project config:
WP-CLI version: 0.23.0
`

Now that you've got WP-CLI, read the [Quick Start](http://wp-cli.org/docs/quick-start/) guide. Or view the [full installation guide](http://wp-cli.org/docs/installing/) with alternative installation methods.

## Support

WP-CLI is a volunteer-led project and can't support every user individually. If you run into trouble, here are some places to look for help:

- [Common issues and their fixes](http://wp-cli.org/docs/common-issues/)
- [Bug reports](http://wp-cli.org/docs/bug-reports/)
- [External resources](http://wp-cli.org/docs/external-resources/)

## Extending

Creating a custom WP-CLI command can be easier than it looks. Read the [commands cookbook](http://wp-cli.org/docs/commands-cookbook/).

## Contributing

Thanks for helping to improve WP-CLI. Please read about [creating an issue](http://wp-cli.org/docs/bug-reports/) or [submitting a pull request](http://wp-cli.org/docs/pull-requests/).

### Contributors
* [Andreas Creten](https://github.com/andreascreten) - founder
* [Cristi Burcă](https://github.com/scribu) - previous maintainer
* [Daniel Bachhuber](https://github.com/danielbachhuber/) - current maintainer

Read more about the project's [Governance](http://wp-cli.org/docs/governance/) and view a [complete list of contributors](https://github.com/wp-cli/wp-cli/contributors).

## Credits

Besides the libraries defined in [composer.json](composer.json), we have used code or ideas from the following projects:

* [Drush](http://drush.ws/) for... a lot of things
* [wpshell](http://code.trac.wordpress.org/browser/wpshell) for `wp shell`
* [Regenerate Thumbnails](http://wordpress.org/plugins/regenerate-thumbnails/) for `wp media regenerate`
* [Search-Replace-DB](https://github.com/interconnectit/Search-Replace-DB) for `wp search-replace`
* [WordPress-CLI-Exporter](https://github.com/Automattic/WordPress-CLI-Exporter) for `wp export`
* [WordPress-CLI-Importer](https://github.com/Automattic/WordPress-CLI-Importer) for `wp import`
* [wordpress-plugin-tests](https://github.com/benbalter/wordpress-plugin-tests/) for `wp scaffold plugin-tests`
