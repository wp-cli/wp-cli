WP-CLI: WordPress Command Line Tools
============================

What is wp-cli
--------------

A tool to control WordPress installations from the command line.

Installing
----------

**Via package manager:**

Ubuntu, Debian: [.deb package](https://github.com/downloads/andreascreten/wp-cli/wp-cli_0.1.deb)

**From source:**

Clone the project:

```
git clone https://github.com/andreascreten/wp-cli.git
cd wp-cli
git submodule update --init
```

Make a symlink to the executable:

```
sudo ln -s /path-to-wp-cli-dir/bin/wp /usr/local/bin/
```

Make a symlink to the autocomplete file (Linux):

```
sudo ln -s /path-to-wp-cli-dir/bin/wp-completion.bash /etc/bash_completion.d/wp
```

Usage
-----

Go into a WordPress root folder:

```
cd /var/www/wp/
```

Typing `wp help` should show you an output similar to this:

```
Example usage:
	wp google-sitemap [build|help] ...
	wp core [update|help] ...
	wp home [help] ...
	wp option [add|update|delete|get|help] ...
	wp plugin [status|activate|deactivate|install|delete|update|help] ...
	wp theme [status|details|activate|help] ...
```

So this tells us which commands are installed: eg. google-sitemap, core, home, ...
Between brackets you can see their sub commands. 

Let's for example try to install the hello dolly plugin from wordpress.org:

```
wp plugin install hello-dolly
```

Output:

```
Installing Hello Dolly (1.5)

Downloading install package from http://downloads.WordPress.org/plugin/hello-dolly.1.5.zip ...
Unpacking the package ...
Installing the plugin ...

Success: The plugin is successfully installed
```

Multisite
---------

On a multisite installation, you need to pass a --blog parameter, so that WP knows which site it's supposed to be operating on:

```
wp theme status --blog=localhost/wp/test
```

If you have a subdomain installation, it would look like this:

```
wp theme status --blog=test.example.com
```

If you're usually working on the same site most of the time, you can put the url of that site in a file called 'wp-cli-blog' in your root WP dir:

```
echo 'test.example.com' > wp-cli-blog
```

Then, you can call `wp` without the --blog parameter again:

```
wp theme status
```

Adding commands
---------------

Adding commands to wp-cli is very easy. You can even add them from within your own plugin.

Each command has its own class, the methods of that class are the sub commands of the command. The base class for the commands is the abstract `WP_CLI_Command`, it handles some essential functionality (like default help for your command).

You can add new commands to the `commands/community` folder in the wp-cli plugin, they will be auto-loaded on startup. You can also add commands from within your plugins by just calling the wp-cli hooks from there.

A wp-cli class is structured like this:

``` php
<?php
/**
 * Implement example command
 *
 * @package wp-cli
 * @subpackage commands/community
 * @author Andreas Creten
 */
class ExampleCommand extends WP_CLI_Command {
	/**
	 * Example method
	 *
	 * @param string $args 
	 * @return void
	 */
	function example($args = array()) {
		// Print a success message
		WP_CLI::success('Success message');
	}
}
```

To register this class under the `example` command, add the following line to the top of your command class file.

``` php
<?php
// Add the command to the wp-cli
WP_CLI::addCommand('example', 'ExampleCommand');
```

This will register the comand `wp example` and the subcommand `wp example example`. If you run `wp example example`, the text `Success: Success message` will be printed to the command line and the script will end.

You can take a look at the example command file in `commands/community/example.php` for more details. For the ways to interact with the command line, you should take a look at the WP_CLI class in the `class-wp-cli.php` file.

If you want to register the command from within your plugin you might want to add a check to see if wp-cli is running. By doing this you can implement your wp-cli command by default, even if wp-cli is not installed on the WordPress installation. You can use the `WP_CLI` constant to check if wp-cli is running:

```php
<?php
if(defined('WP_CLI') && WP_CLI) {
	// Define and register your command in here
}
```

**Please share the commands you make, issue a pull request to get them included in wp-cli by default.**

Contributors
------------

- [Contributor list](https://github.com/andreascreten/wp-cli/contributors)
- [Contributor impact](https://github.com/andreascreten/wp-cli/graphs/impact)

Requirements
------------

 * PHP >= 5.3
