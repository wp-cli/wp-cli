<?php

return [
	'path'                 => [
		'runtime' => '=<path>',
		'file'    => '<path>',
		'desc'    => 'Path to the WordPress files.',
	],

	'url'                  => [
		'runtime' => '=<url>',
		'file'    => '<url>',
		'desc'    => 'Pretend request came from given URL. In multisite, this argument is how the target site is specified.',
	],

	'ssh'                  => [
		'runtime' => '=[<scheme>:][<user>@]<host|container>[:<port>][<path>]',
		'file'    => '[<scheme>:][<user>@]<host|container>[:<port>][<path>]',
		'desc'    => 'Perform operation against a remote server over SSH (or a container using scheme of "docker", "docker-compose", "docker-compose-run", "vagrant").',
	],

	'ssh-args'             => [
		'runtime'  => '=<args>',
		'file'     => '<args>',
		'desc'     => 'Pass additional arguments to SSH (or other tools specified by --ssh scheme).',
		'multiple' => true,
		'default'  => [],
	],

	'http'                 => [
		'runtime' => '=<http>',
		'file'    => '<http>',
		'desc'    => 'Perform operation against a remote WordPress installation over HTTP.',
	],

	'blog'                 => [
		'deprecated' => 'Use --url instead.',
		'runtime'    => '=<url>',
	],

	'user'                 => [
		'runtime' => '=<id|login|email>',
		'file'    => '<id|login|email>',
		'desc'    => 'Set the WordPress user.',
	],

	'skip-plugins'         => [
		'runtime' => '[=<plugins>]',
		'file'    => '<list>',
		'desc'    => 'Skip loading all plugins, or a comma-separated list of plugins. Note: mu-plugins are still loaded.',
		'default' => '',
	],

	'skip-themes'          => [
		'runtime' => '[=<themes>]',
		'file'    => '<list>',
		'desc'    => 'Skip loading all themes, or a comma-separated list of themes.',
		'default' => '',
	],

	'skip-packages'        => [
		'runtime' => '',
		'file'    => '<bool>',
		'desc'    => 'Skip loading all installed packages.',
		'default' => false,
	],

	'require'              => [
		'runtime'  => '=<path>',
		'file'     => '<path>',
		'desc'     => 'Load PHP file before running the command (may be used more than once).',
		'multiple' => true,
		'default'  => [],
	],

	'exec'                 => [
		'runtime'  => '=<php-code>',
		'file'     => '<php-code>',
		'desc'     => 'Execute PHP code before running the command (may be used more than once).',
		'multiple' => true,
		'default'  => [],
	],

	'trust-project-config' => [
		'runtime'  => '[=<bool|path>]',
		'file'     => '<bool|path>',
		'desc'     => 'Trust the project-level wp-cli.yml (or wp-cli.local.yml) file so that the require, exec, env, ssh-args and connection (ssh, http, ssh_config, proxyjump, key) settings and the aliases it introduces are acted upon. Accepts true, false, or a path (or list of paths) to a trusted wp-cli.yml file or project directory. Precedence: this parameter, then the global and system config files, then the trust store (trusted-configs.json next to the global config file), then the WP_CLI_TRUST_PROJECT_CONFIG environment variable, then an interactive prompt. Without a TTY an undecided project config is an error, so in CI prefer --trust-project-config=<path> over =true.',
		'multiple' => true,
		'default'  => [],
	],

	'context'              => [
		'runtime' => '=<context>',
		'file'    => '<context>',
		'default' => 'auto',
		'desc'    => 'Load WordPress in a given context.',
	],

	'locale'               => [
		'file'    => '<locale>',
		'desc'    => 'Set the locale for WordPress when WP-CLI loads it (e.g., en_US, de_DE).',
		'default' => '',
	],

	'disabled_commands'    => [
		'file'     => '<list>',
		'default'  => [],
		'multiple' => true,
		'desc'     => '(Sub)commands to disable.',
	],

	'color'                => [
		'runtime' => true,
		'file'    => '<bool>',
		'default' => 'auto',
		'desc'    => 'Whether to colorize the output.',
	],

	'debug'                => [
		'runtime' => '[=<group>]',
		'file'    => '<group>',
		'default' => false,
		'desc'    => 'Show all PHP errors and add verbosity to WP-CLI output. Built-in groups include: bootstrap, commandfactory, and help.',
	],

	'prompt'               => [
		'runtime' => '[=<assoc>]',
		'file'    => false,
		'default' => false,
		'desc'    => 'Prompt the user to enter values for all command arguments, or a subset specified as comma-separated values.',
	],

	'quiet'                => [
		'runtime' => '',
		'file'    => '<bool>',
		'default' => false,
		'desc'    => 'Suppress informational messages.',
	],

	'apache_modules'       => [
		'file'     => '<list>',
		'desc'     => 'List of Apache Modules that are to be reported as loaded.',
		'multiple' => true,
		'default'  => [],
	],

	# --allow-root => (NOT RECOMMENDED) Allow wp-cli to run as root. This poses
	# a security risk, so you probably do not want to do this.
	'allow-root'           => [
		'file'    => false, # Explicit. Just in case the default changes.
		'runtime' => '',
		'hidden'  => true,
	],

	'alias'                => [
		'runtime'  => '=<name>',
		'file'     => '<name>',
		'desc'     => 'Name of the alias to use. Aliases can reference local WordPress installations or remote SSH connections. Aliases are defined in the wp-cli.yml file.',
		'multiple' => false,
		'default'  => '',
	],

	'assume-https'         => [
		'runtime' => '',
		'file'    => '<bool>',
		'default' => false,
		'desc'    => 'Set $_SERVER[\'HTTPS\'] to make WordPress treat the site as HTTPS. Use when WordPress is behind an HTTPS proxy or load balancer.',
	],

];
