# PHP Coding Standards Setup

This document explains how to set up PHP CodeSniffer (PHPCS) and PHP Code Beautifier and Fixer (PHPCBF) to automatically check and fix coding standards issues in the WP-CLI project.

## Installation

1. Install PHPCS and related tools via Composer:

```bash
composer require --dev squizlabs/php_codesniffer wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer
```

2. Set up PHPCS to use WordPress coding standards:

```bash
vendor/bin/phpcs --config-set installed_paths vendor/wp-coding-standards/wpcs
```

## Usage

### Checking for issues

To check if your code complies with the project's coding standards:

```bash
vendor/bin/phpcs --standard=phpcs.xml.dist path/to/your/file.php
```

Or to check multiple files:

```bash
vendor/bin/phpcs --standard=phpcs.xml.dist path/to/directory
```

### Fixing issues automatically

To automatically fix coding standards issues:

```bash
vendor/bin/phpcbf --standard=phpcs.xml.dist path/to/your/file.php
```

## Common Issues and How to Fix Them

### Array Alignment Issues

PHPCS often flags issues with array key alignment. For example:

```php
// Incorrect
$array = array(
    'short'   => 'value',
    'long_key' => 'value',
);

// Correct
$array = array(
    'short'    => 'value',
    'long_key' => 'value',
);
```

### Equals Sign Alignment

Variables should have their equals signs aligned:

```php
// Incorrect
$short = 'value';
$long_variable = 'value';

// Correct
$short        = 'value';
$long_variable = 'value';
```

### Error Silencing (@ Operator)

Avoid using the @ operator to silence errors. Instead, implement proper error handling:

```php
// Incorrect
$data = @json_encode($array);

// Correct
$data = json_encode($array);
if (false === $data) {
    // Handle error
}
```

## Pre-commit Hook

A pre-commit hook has been set up in this repository to automatically check for PHPCS issues before each commit. If you encounter issues with the hook, you can:

1. Run PHPCS manually to identify issues
2. Use PHPCBF to automatically fix some issues
3. Fix the remaining issues manually
4. Commit your changes

## Further Resources

- [PHP CodeSniffer Documentation](https://github.com/squizlabs/PHP_CodeSniffer/wiki)
- [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- [WP-CLI Coding Standards](https://make.wordpress.org/cli/handbook/guides/coding-standards/)
