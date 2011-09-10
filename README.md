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

Usage
-----

In your terminal, go into the wp-cli folder.

Typing the following command: `./wp help`,  will show you an output similar to this:

```
Example usage:
    wp google-sitemap [build|help] ...
    wp core [update|help] ...
    wp home [help] ...
    wp plugins [status|activate|deactivate|install|delete|update|help] ...
```

So this tells us that there are 4 commands installed: google-sitemap, core, home and plugins.
Between brackets you can see their sub commands. 


Let's for example try to update the hello dolly plugin from Wordpress: `./wp plugins install hello-dolly`.
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

Requirements
------------

 * PHP >= 5.3