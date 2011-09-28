WP-CLI: Wordpress Command Line Tools
============================

What is wp-cli
--------------

A command line tool to do maintenance work on a Wordpress install from the command line.

Installing
----------

Installing wp-cli is extremely simple: 
	
1. Place the `wp-cli` folder in your Wordpress root (on the same level as `wp-admin` and `wp-content`).
1. That's it!

Commands
--------

- `wp core` - Update the Wordpress core
- `wp home` - Open the wp-cli project on Github
- `wp option ...` - Manipulate the Wordpress options
- `wp plugins ...` - Do cool things with the installed plugins
- `wp theme ...` - Get details on the installed and current theme

Usage
-----

In your terminal, go into the wp-cli folder.

Typing the following command: `./wp help`,  will show you an output similar to this:

```
Example usage:
	wp google-sitemap [build|help] ...
	wp core [update|help] ...
	wp home [help] ...
	wp option [add|update|delete|get|help] ...
	wp plugins [status|activate|deactivate|install|delete|update|help] ...
	wp theme [list|details|activate|help] ...
```

So this tells us that there are 4 commands installed: google-sitemap, core, home and plugins.
Between brackets you can see their sub commands. 

Let's for example try to install the hello dolly plugin from Wordpress: `./wp plugins install hello-dolly`.
Output:

```
Installing Hello Dolly (1.5)

Downloading install package from http://downloads.wordpress.org/plugin/hello-dolly.1.5.zip ...
Unpacking the package ...
Installing the plugin ...

Success: The plugin is successfully installed
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
 * @subpackage commands/cummunity
 * @author Andreas Creten
 */
class ExampleCommand extends WP_CLI_Command {
	/**
	 * Example method
	 *
	 * @param string $args 
	 * @return void
	 * @author Andreas Creten
	 */
	function example($args) {
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

If you want to register the command from within your plugin you might want to add a check to see if wp-cli is running. By doing this you can implement your wp-cli command by default, even if wp-cli is not installed on the Wordpress installation. You can use the `WP_CLI` constant to check if wp-cli is running:

```php
<?php
if(defined('WP_CLI') && WP_CLI) {
	// Define and register your command in here
}
```

**Please share the commands you make, issue a pull request to get them included in wp-cli by default.**

Requirements
------------

 * PHP >= 5.3