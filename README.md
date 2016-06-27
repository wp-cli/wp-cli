WP-CLI
======

[WP-CLI](https://wp-cli.org/) is a set of command-line tools for managing [WordPress](https://wordpress.org/) installations. You can update plugins, configure multisite installs and much more, without using a web browser.

To stay up to date, follow [@wpcli on Twitter](https://twitter.com/wpcli) or [sign up for our email newsletter](http://wp-cli.us13.list-manage.com/subscribe?u=0615e4d18f213891fc000adfd&id=8c61d7641e).

[![Build Status](https://travis-ci.org/wp-cli/wp-cli.png?branch=master)](https://travis-ci.org/wp-cli/wp-cli) [![Dependency Status](https://gemnasium.com/badges/github.com/wp-cli/wp-cli.svg)](https://gemnasium.com/github.com/wp-cli/wp-cli)

<div style="
	border: 1px solid #7AD03A;
	-webkit-border-radius: 5px;
	-moz-border-radius: 5px;
	border-radius: 5px;
	padding-left: 10px;
	padding-right: 10px;
">
	<p><strong>A more RESTful WP-CLI</strong> aims to unlocking the potential of the WP REST API at the command line. Project backed by Pressed, Chris Lema, Human Made, Pagely, Pantheon and many others. <a href="https://wp-cli.org/restful/">Learn more &rarr;</a></p>
</div>

Quick links: [Using](#using) &#124; [Installing](#installing) &#124; [Support](#support) &#124; [Extending](#extending) &#124; [Contributing](#contributing) &#124; [Credits](#credits)

## Using

WP-CLI's goal is to provide a command-line interface for any action you might want to perform in the WordPress admin. For instance, `wp plugin install` ([doc](https://wp-cli.org/commands/plugin/install/)) lets you install and activate a WordPress plugin:

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

WP-CLI also includes commands for many things you can't do in the WordPress admin. For example, `wp transient delete-all` ([doc](https://wp-cli.org/commands/transient/delete-all/)) lets you delete one or all transients:

```
$ wp transient delete-all
Success: 34 transients deleted from the database.
```

For a more complete introduction to using WP-CLI, read the [Quick Start guide](https://wp-cli.org/docs/quick-start/).

Already feel comfortable with the basics? Jump into the [complete list of commands](https://wp-cli.org/commands/) for detailed information on managing themes and plugins, importing and exporting data, performing database search-replace operations and more.

## Installing

Downloading the Phar file is our recommended installation method. Should you need, see also our documentation on [alternative installation methods](https://wp-cli.org/docs/installing/).

Before installing WP-CLI, please make sure your environment meets the minimum requirements:

- UNIX-like environment (OS X, Linux, FreeBSD, Cygwin); limited support in Windows environment
- PHP 5.3.29 or later
- WordPress 3.7 or later

Once you've verified requirements, download the [wp-cli.phar](https://raw.github.com/wp-cli/builds/gh-pages/phar/wp-cli.phar) file using `wget` or `curl`:

```
$ curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
```

Next, check if it is working:

```
$ php wp-cli.phar --info
```

To use WP-CLI from the command line by typing `wp`, make the file executable and move it to somewhere in your PATH. For example:

```
$ chmod +x wp-cli.phar
$ sudo mv wp-cli.phar /usr/local/bin/wp
```

If WP-CLI was installed successfully, you should see something like this when you run `wp --info`:

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

You can update WP-CLI with `wp cli update` ([doc](https://wp-cli.org/commands/cli/update/)), or by repeating the installation steps.

## Support

WP-CLI's maintainers and project contributors do their best to respond to all new issues in a timely manner. To make the best use of their volunteered time, please first see if there may be an answer to your question in one of the following resources:

- [Common issues and their fixes](https://wp-cli.org/docs/common-issues/)
- [Best practices for submitting a bug report](https://wp-cli.org/docs/bug-reports/)
- [Documentation portal](https://wp-cli.org/docs/)

If you have a WordPress.org account, you may also consider joining the `#cli` channel on the [WordPress.org Slack organization](https://make.wordpress.org/chat/).

## Extending

A **command** is an atomic unit of WP-CLI functionality. `wp plugin install` ([doc](https://wp-cli.org/commands/plugin/install/)) is one command. `wp plugin activate` ([doc](https://wp-cli.org/commands/plugin/activate/)) is another.

WP-CLI comes with dozens of commands. It's easier than it looks to create a custom WP-CLI command. Read the [commands cookbook](https://wp-cli.org/docs/commands-cookbook/) to learn more.

## Contributing

To get involved, please first read about [creating an issue](https://wp-cli.org/docs/bug-reports/) or [submitting a pull request](https://wp-cli.org/docs/pull-requests/).

### Leadership

* [Daniel Bachhuber](https://github.com/danielbachhuber/) - current maintainer
* [Cristi Burcă](https://github.com/scribu) - previous maintainer
* [Andreas Creten](https://github.com/andreascreten) - founder

Read more about the project's [governance](https://wp-cli.org/docs/governance/) and view a [complete list of contributors](https://github.com/wp-cli/wp-cli/contributors).

## Credits

Besides the libraries defined in [composer.json](composer.json), we have used code or ideas from the following projects:

* [Drush](http://drush.ws/) for... a lot of things
* [wpshell](http://code.trac.wordpress.org/browser/wpshell) for `wp shell`
* [Regenerate Thumbnails](http://wordpress.org/plugins/regenerate-thumbnails/) for `wp media regenerate`
* [Search-Replace-DB](https://github.com/interconnectit/Search-Replace-DB) for `wp search-replace`
* [WordPress-CLI-Exporter](https://github.com/Automattic/WordPress-CLI-Exporter) for `wp export`
* [WordPress-CLI-Importer](https://github.com/Automattic/WordPress-CLI-Importer) for `wp import`
* [wordpress-plugin-tests](https://github.com/benbalter/wordpress-plugin-tests/) for `wp scaffold plugin-tests`
