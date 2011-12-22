What is wp-cli?
--------------

A set of tools for controlling WordPress installations from the command line.

Installing
----------

**Via PEAR:**

```sh
pear config-set auto_discover 1
sudo pear install andreascreten.github.com/wp-cli/wpcli
```

**Via GIT:**

```sh
git clone --recurse-submodules git://github.com/andreascreten/wp-cli.git ~/git/wp-cli
cd ~/git/wp-cli
sudo utils/build-dev
```

You can replace `~/git/wp-cli` with whatever you want.


Using
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
You can find more information about adding commands in the [Commands Cookbook](https://github.com/andreascreten/wp-cli/wiki/Commands-Cookbook) on our Wiki.

**Please share the commands you make, issue a pull request to get them included in wp-cli by default.**

Changelog
---------------

**0.4**

- added `wp eval` and `wp eval-file`
- added `wp export`
- added `wp core install`
- added `--dev` flag to `wp plugin install`
- added `wp plugin uninstall`

**0.3**

- added `wp sql`
- improved `wp option`
- pear installer

**0.2**

- added multisite support
- improved `wp plugin` and `wp theme`
- added `wp generate`
- added `wp core version`
- added `wp --version`
- added bash completion script

**0.1**

- initial release

Contributors
------------

- [Contributor list](https://github.com/andreascreten/wp-cli/contributors)
- [Contributor impact](https://github.com/andreascreten/wp-cli/graphs/impact)
