WP-CLI
======

[WP-CLI](http://wp-cli.org/) is a set of command-line tools for managing WordPress installations. You can update plugins, set up multisite installs and much more, without using a web browser.

[![Build Status](https://travis-ci.org/wp-cli/wp-cli.png?branch=master)](https://travis-ci.org/wp-cli/wp-cli)

Quick links: [Using](#using) | [Installing](#installing) | [Support](#support) | [Extending](#extending) | [Contributing](#contributing) | [Credits](#credits)

## Using

The goal of WP-CLI is to provide a command-line interface for any action you can perform in the WordPress admin. The project also includes commands for many actions you can't perform in the WordPress admin.

For instance, `wp plugin install` ([doc](http://wp-cli.org/commands/plugin/install/)) lets you install and activate a WordPress plugin:

```
$ wp plugin install rest-api --activate
Installing WordPress REST API (Version 2) (2.0-beta13)
Downloading install package from https://downloads.wordpress.org/plugin/rest-api.2.0-beta13.zip...
Unpacking the package...
Installing the plugin...
Plugin installed successfully.
Activating 'rest-api'...
Success: Plugin 'rest-api' activated.
```

`wp transient` ([doc](http://wp-cli.org/commands/transient/)) lets you delete one or all transients:

```
$ wp transient delete-all
Success: 34 transients deleted from the database.
```

For a complete introduction, the [Quick Start guide](http://wp-cli.org/docs/quick-start/). If you already feel comfortable with the basics, jump into the [complete list of commands](http://wp-cli.org/commands/) for managing themes and plugins, importing and exporting data, performing database search-replace operations and more.

## Installing

The recommended way to install WP-CLI is to download the Phar build, mark it executable and place it in your PATH.

Before you install though, please make sure your environment meets the minimum requirements:

- UNIX-like environment (OS X, Linux, FreeBSD, Cygwin); limited support in Windows environment
- PHP 5.3.29 or later
- WordPress 3.7 or later

Download the [wp-cli.phar](https://raw.github.com/wp-cli/builds/gh-pages/phar/wp-cli.phar) using `wget` or `curl`. For example:

```
$ curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
```

Check if it is working:

```
$ php wp-cli.phar --info
```

To use it from the command line by typing `wp`, make the file executable and move it to somewhere in your PATH. For example:

```
$ chmod +x wp-cli.phar
$ sudo mv wp-cli.phar /usr/local/bin/wp
```

If WP-CLI installed successfully, you should see something like this when you run `wp --info`:

```
$ wp --info
PHP binary:    /usr/bin/php5
PHP version:    5.5.9-1ubuntu4.14
php.ini used:   /etc/php5/cli/php.ini
WP-CLI root dir:        /home/wp-cli/.wp-cli
WP-CLI packages dir:    /home/wp-cli/.wp-cli/packages/
WP-CLI global config:   /home/wp-cli/.wp-cli/config.yml
WP-CLI project config:
WP-CLI version: 0.23.0
```

Now that you've got WP-CLI, read the [Quick Start](http://wp-cli.org/docs/quick-start/) guide. Or view the [full installation guide](http://wp-cli.org/docs/installing/) with alternative installation methods.

## Support

WP-CLI's project maintainers do their best to respond to all bug reports and configuration errors within reason and the constraints on their time. Before requesting help, please read to find a solution to your problem in the following resources:

- [Common issues and their fixes](http://wp-cli.org/docs/common-issues/)
- [Submit a bug report](http://wp-cli.org/docs/bug-reports/)
- [External resources](http://wp-cli.org/docs/external-resources/)

## Extending

A **command** is an atomic unit of WP-CLI functionality. `wp plugin install` ([doc](http://wp-cli.org/commands/plugin/install/)) is one command. `wp plugin activate` ([doc](http://wp-cli.org/commands/plugin/activate/)) is another.

WP-CLI comes with dozens of commands. But it's easier than it looks to create a custom WP-CLI command. Read the [commands cookbook](http://wp-cli.org/docs/commands-cookbook/) to learn more.

## Contributing

Thanks for helping to improve WP-CLI. Please read about [creating an issue](http://wp-cli.org/docs/bug-reports/) or [submitting a pull request](http://wp-cli.org/docs/pull-requests/).

### Leadership
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
