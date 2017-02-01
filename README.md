WP-CLI
======

[WP-CLI](https://wp-cli.org/) is a set of command-line tools for managing [WordPress](https://wordpress.org/) installations. You can update plugins, configure multisite installs and much more, without using a web browser.

For announcements, follow [@wpcli on Twitter](https://twitter.com/wpcli) or [sign up for our email newsletter](http://wp-cli.us13.list-manage.com/subscribe?u=0615e4d18f213891fc000adfd&id=8c61d7641e). [Check out the roadmap](https://wp-cli.org/docs/roadmap/) for an overview of what's planned for upcoming releases.

[![Build Status](https://travis-ci.org/wp-cli/wp-cli.svg?branch=master)](https://travis-ci.org/wp-cli/wp-cli) [![Dependency Status](https://gemnasium.com/badges/github.com/wp-cli/wp-cli.svg)](https://gemnasium.com/github.com/wp-cli/wp-cli) [![Average time to resolve an issue](http://isitmaintained.com/badge/resolution/wp-cli/wp-cli.svg)](http://isitmaintained.com/project/wp-cli/wp-cli "Average time to resolve an issue") [![Percentage of issues still open](http://isitmaintained.com/badge/open/wp-cli/wp-cli.svg)](http://isitmaintained.com/project/wp-cli/wp-cli "Percentage of issues still open")

Quick links: [Using](#using) &#124; [Installing](#installing) &#124; [Support](#support) &#124; [Extending](#extending) &#124; [Contributing](#contributing) &#124; [Credits](#credits)

## Using

WP-CLI's goal is to provide a command-line interface for any action you might want to perform in the WordPress admin. For instance, `wp plugin install --activate` ([doc](https://wp-cli.org/commands/plugin/install/)) lets you install and activate a WordPress plugin:

```bash
$ wp plugin install rest-api --activate
Installing WordPress REST API (Version 2) (2.0-beta13)
Downloading install package from https://downloads.wordpress.org/plugin/rest-api.2.0-beta13.zip...
Unpacking the package...
Installing the plugin...
Plugin installed successfully.
Activating 'rest-api'...
Success: Plugin 'rest-api' activated.
```

WP-CLI also includes commands for many things you can't do in the WordPress admin. For example, `wp transient delete --all` ([doc](https://wp-cli.org/commands/transient/delete/)) lets you delete one or all transients:

```bash
$ wp transient delete --all
Success: 34 transients deleted from the database.
```

For a more complete introduction to using WP-CLI, read the [Quick Start guide](https://wp-cli.org/docs/quick-start/). Or, catch up with [shell friends](https://wp-cli.org/docs/shell-friends/) to learn about helpful command line utilities.

Already feel comfortable with the basics? Jump into the [complete list of commands](https://wp-cli.org/commands/) for detailed information on managing themes and plugins, importing and exporting data, performing database search-replace operations and more.

## Installing

Downloading the Phar file is our recommended installation method. Should you need, see also our documentation on [alternative installation methods](https://wp-cli.org/docs/installing/).

Before installing WP-CLI, please make sure your environment meets the minimum requirements:

- UNIX-like environment (OS X, Linux, FreeBSD, Cygwin); limited support in Windows environment
- PHP 5.3.29 or later
- WordPress 3.7 or later

Once you've verified requirements, download the [wp-cli.phar](https://raw.github.com/wp-cli/builds/gh-pages/phar/wp-cli.phar) file using `wget` or `curl`:

```bash
$ curl -O https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
```

Next, check if it is working:

```bash
$ php wp-cli.phar --info
```

To use WP-CLI from the command line by typing `wp`, make the file executable and move it to somewhere in your PATH. For example:

```bash
$ chmod +x wp-cli.phar
$ sudo mv wp-cli.phar /usr/local/bin/wp
```

If WP-CLI was installed successfully, you should see something like this when you run `wp --info`:

```bash
$ wp --info
PHP binary:    /usr/bin/php5
PHP version:    5.5.9-1ubuntu4.14
php.ini used:   /etc/php5/cli/php.ini
WP-CLI root dir:        /home/wp-cli/.wp-cli
WP-CLI packages dir:    /home/wp-cli/.wp-cli/packages/
WP-CLI global config:   /home/wp-cli/.wp-cli/config.yml
WP-CLI project config:
WP-CLI version: 1.1.0
```

### Updating

You can update WP-CLI with `wp cli update` ([doc](https://wp-cli.org/commands/cli/update/)), or by repeating the installation steps.

If WP-CLI is owned by root or another system user, you'll need to run `sudo wp cli update`.

Want to live life on the edge? Run `wp cli update --nightly` to use the latest nightly build of WP-CLI. The nightly build is more or less stable enough for you to use in your development environment, and always includes the latest and greatest WP-CLI features.

### Tab completions

WP-CLI also comes with a tab completion script for Bash and ZSH. Just download [wp-completion.bash](https://raw.githubusercontent.com/wp-cli/wp-cli/master/utils/wp-completion.bash) and source it from `~/.bash_profile`:

```bash
source /FULL/PATH/TO/wp-completion.bash
```

Don't forget to run `source ~/.bash_profile` afterwards.

If using zsh for your shell, you may need to load and start `bashcompinit` before sourcing. Put the following in your `.zshrc`:

```bash
autoload bashcompinit
bashcompinit
source /FULL/PATH/TO/wp-completion.bash
```

## Support

WP-CLI's maintainers and project contributors are volunteers, and have limited availability to address general support questions. The [current version of WP-CLI](http://wp-cli.org/docs/roadmap/) is the only officially supported version.

When looking for support, please first look for an answer in one of the following resources:

- [Common issues and their fixes](https://wp-cli.org/docs/common-issues/)
- [Documentation portal](https://wp-cli.org/docs/)
- [Open or closed issues on Github](https://github.com/wp-cli/wp-cli/issues?utf8=%E2%9C%93&q=is%3Aissue)
- [runcommand tips](https://runcommand.io/tips/)
- [WordPress StackExchange forums](http://wordpress.stackexchange.com/questions/tagged/wp-cli)

Need help with a project related to work? Professional users may want to consider [runcommand premium support](https://runcommand.io/pricing/). Alternatively, join the `#cli` channel on the [WordPress.org Slack organization](https://make.wordpress.org/chat/) to see if a community member might have an answer for you.

Github issues are meant for tracking enhancements and bugs of existing commands, not general support. Before submitting a bug report, please [review our best practices](https://wp-cli.org/docs/bug-reports/) to help ensure your issue is addressed in a timely manner.

Please do not ask support questions on Twitter. Twitter isn't an acceptable venue for support because: 1) it's hard to hold conversations in under 140 characters, and 2) Twitter isn't a place where someone with your same question can search for an answer in a prior conversation.

Remember, libre != gratis; the open source license grants you the freedom to use and modify, but not commitments of other people's time. Please be respectful, and set your expectations accordingly.

## Extending

A **command** is an atomic unit of WP-CLI functionality. `wp plugin install` ([doc](https://wp-cli.org/commands/plugin/install/)) is one command. `wp plugin activate` ([doc](https://wp-cli.org/commands/plugin/activate/)) is another.

WP-CLI supports registering any callable class, function, or closure as a command. It reads usage details from the callback's PHPdoc. `WP_CLI::add_command()` ([doc](https://wp-cli.org/docs/internal-api/wp-cli-add-command/)) is used for both internal and third-party command registration.

```php
/**
 * Delete an option from the database.
 *
 * Returns an error if the option didn't exist.
 *
 * ## OPTIONS
 *
 * <key>
 * : Key for the option.
 *
 * ## EXAMPLES
 *
 *     $ wp option delete my_option
 *     Success: Deleted 'my_option' option.
 */
$delete_option_cmd = function( $args ) {
	list( $key ) = $args;

	if ( ! delete_option( $key ) ) {
		WP_CLI::error( "Could not delete '$key' option. Does it exist?" );
	} else {
		WP_CLI::success( "Deleted '$key' option." );
	}
};
WP_CLI::add_command( 'option delete', $delete_option_cmd );
```

WP-CLI comes with dozens of commands. It's easier than it looks to create a custom WP-CLI command. Read the [commands cookbook](https://wp-cli.org/docs/commands-cookbook/) to learn more. Browse the [internal API docs](https://wp-cli.org/docs/internal-api/) to discover a variety of helpful functions you can use in your custom WP-CLI command.

## Contributing

Welcome and thanks!

We appreciate you taking the initiative to contribute to WP-CLI. It’s because of you, and the community around you, that WP-CLI is such a great project.

**Contributing isn’t limited to just code.** We encourage you to contribute in the way that best fits your abilities, by writing tutorials, giving a demo at your local meetup, helping other users with their support questions, or revising our documentation.

Please take a moment to [read these guidelines at depth](https://wp-cli.org/docs/contributing/). Following them helps to communicate that you respect the time of the other contributors to the project. In turn, they’ll do their best to reciprocate that respect when working with you, across timezones and around the world.

## Leadership

WP-CLI is led by these individuals:

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
