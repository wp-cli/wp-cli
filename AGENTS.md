# Instructions

This package is part of WP-CLI, the official command line interface for WordPress. For a detailed explanation of the project structure and development workflow, please refer to the main @README.md file.

## Best Practices for Code Contributions

When contributing to this package, please adhere to the following guidelines:

* **Follow Existing Conventions:** Before writing any code, analyze the existing codebase in this package to understand the coding style, naming conventions, and architectural patterns.
* **Focus on the Package's Scope:** All changes should be relevant to the functionality of the package.
* **Write Tests:** All new features and bug fixes must be accompanied by acceptance tests using Behat. You can find the existing tests in the `features/` directory. There may be PHPUnit unit tests as well in the `tests/` directory.
* **Update Documentation:** If your changes affect the user-facing functionality, please update the relevant inline code documentation.

### Building and running

Before submitting any changes, it is crucial to validate them by running the full suite of static code analysis and tests. To run the full suite of checks, execute the following command: `composer test`.

This single command ensures that your changes meet all the quality gates of the project. While you can run the individual steps separately, it is highly recommended to use this single command to ensure a comprehensive validation.

### Useful Composer Commands

The project uses Composer to manage dependencies and run scripts. The following commands are available:

* `composer install`: Install dependencies.
* `composer test`: Run the full test suite, including linting, code style checks, static analysis, and unit/behavior tests.
* `composer lint`: Check for syntax errors.
* `composer phpcs`: Check for code style violations.
* `composer phpcbf`: Automatically fix code style violations.
* `composer phpstan`: Run static analysis.
* `composer phpunit`: Run unit tests.
* `composer behat`: Run behavior-driven tests.

### Coding Style

The project follows the `WP_CLI_CS` coding standard, which is enforced by PHP_CodeSniffer. The configuration can be found in `phpcs.xml.dist`. Before submitting any code, please run `composer phpcs` to check for violations and `composer phpcbf` to automatically fix them.

## Documentation

The `README.md` file might be generated dynamically from the project's codebase using `wp scaffold package-readme` ([doc](https://github.com/wp-cli/scaffold-package-command#wp-scaffold-package-readme)). In that case, changes need to be made against the corresponding part of the codebase.

### Inline Documentation

Only write high-value comments if at all. Avoid talking to the user through comments.

## Testing

The project has a comprehensive test suite that includes unit tests, behavior-driven tests, and static analysis.

* **Unit tests** are written with PHPUnit and can be found in the `tests/` directory. The configuration is in `phpunit.xml.dist`.
* **Behavior-driven tests** are written with Behat and can be found in the `features/` directory. The configuration is in `behat.yml`.
* **Static analysis** is performed with PHPStan.

All tests are run on GitHub Actions for every pull request.

When writing tests, aim to follow existing patterns. Key conventions include:

* When adding tests, first examine existing tests to understand and conform to established conventions.
* For unit tests, extend the base `WP_CLI\Tests\TestCase` test class.
* For Behat tests, only WP-CLI commands installed in `composer.json` can be run.

### Behat Steps

WP-CLI makes use of a Behat-based testing framework and provides a set of custom step definitions to write feature tests.

> **Note:** If you are expecting an error output in a test, you need to use `When I try ...` instead of `When I run ...` .

#### Given

* `Given an empty directory` - Creates an empty directory.
* `Given /^an? (empty|non-existent) ([^\s]+) directory$/` - Creates or deletes a specific directory.
* `Given an empty cache` - Clears the WP-CLI cache directory.
* `Given /^an? ([^\s]+) (file|cache file):$/` - Creates a file with the given contents.
* `Given /^"([^"]+)" replaced with "([^"]+)" in the ([^\s]+) file$/` - Search and replace a string in a file using regex.
* `Given /^that HTTP requests to (.*?) will respond with:$/` - Mock HTTP requests to a given URL.
* `Given WP files` - Download WordPress files without installing.
* `Given wp-config.php` - Create a wp-config.php file using `wp config create`.
* `Given a database` - Creates an empty database.
* `Given a WP install(ation)` - Installs WordPress.
* `Given a WP install(ation) in :subdir` - Installs WordPress in a given directory.
* `Given a WP install(ation) with Composer` - Installs WordPress with Composer.
* `Given a WP install(ation) with Composer and a custom vendor directory :vendor_directory` - Installs WordPress with Composer and a custom vendor directory.
* `Given /^a WP multisite (subdirectory|subdomain)?\s?(install|installation)$/` - Installs WordPress Multisite.
* `Given these installed and active plugins:` - Installs and activates one or more plugins.
* `Given a custom wp-content directory` - Configure a custom `wp-content` directory.
* `Given download:` - Download multiple files into the given destinations.
* `Given /^save (STDOUT|STDERR) ([\'].+[^\'])?\s?as \{(\w+)\}$/` - Store STDOUT or STDERR contents in a variable.
* `Given /^a new Phar with (?:the same version|version "([^"]+)")$/` - Build a new WP-CLI Phar file with a given version.
* `Given /^a downloaded Phar with (?:the same version|version "([^"]+)")$/` - Download a specific WP-CLI Phar version from GitHub.
* `Given /^save the (.+) file ([\'].+[^\'])? as \{(\w+)\}$/` - Stores the contents of the given file in a variable.
* `Given a misconfigured WP_CONTENT_DIR constant directory` - Modify wp-config.php to set `WP_CONTENT_DIR` to an empty string.
* `Given a dependency on current wp-cli` - Add `wp-cli/wp-cli` as a Composer dependency.
* `Given a PHP built-in web server` - Start a PHP built-in web server in the current directory.
* `Given a PHP built-in web server to serve :subdir` - Start a PHP built-in web server in the given subdirectory.

#### When

* ``When /^I launch in the background `([^`]+)`$/`` - Launch a given command in the background.
* ``When /^I (run|try) `([^`]+)`$/`` - Run or try a given command.
* ``When /^I (run|try) `([^`]+)` from '([^\s]+)'$/`` - Run or try a given command in a subdirectory.
* `When /^I (run|try) the previous command again$/` - Run or try the previous command again.

#### Then

* `Then /^the return code should( not)? be (\d+)$/` - Expect a specific exit code of the previous command.
* `Then /^(STDOUT|STDERR) should( strictly)? (be|contain|not contain):$/` - Check the contents of STDOUT or STDERR.
* `Then /^(STDOUT|STDERR) should be a number$/` - Expect STDOUT or STDERR to be a numeric value.
* `Then /^(STDOUT|STDERR) should not be a number$/` - Expect STDOUT or STDERR to not be a numeric value.
* `Then /^STDOUT should be a table containing rows:$/` - Expect STDOUT to be a table containing the given rows.
* `Then /^STDOUT should end with a table containing rows:$/` - Expect STDOUT to end with a table containing the given rows.
* `Then /^STDOUT should be JSON containing:$/` - Expect valid JSON output in STDOUT.
* `Then /^STDOUT should be a JSON array containing:$/` - Expect valid JSON array output in STDOUT.
* `Then /^STDOUT should be CSV containing:$/` - Expect STDOUT to be CSV containing certain values.
* `Then /^STDOUT should be YAML containing:$/` - Expect STDOUT to be YAML containing certain content.
* `Then /^(STDOUT|STDERR) should be empty$/` - Expect STDOUT or STDERR to be empty.
* `Then /^(STDOUT|STDERR) should not be empty$/` - Expect STDOUT or STDERR not to be empty.
* `Then /^(STDOUT|STDERR) should be a version string (&lt;|&lt;=|&gt;|&gt;=|==|=|&lt;&gt;) ([+\w.{}-]+)$/` - Expect STDOUT or STDERR to be a version string comparing to the given version.
* `Then /^the (.+) (file|directory) should( strictly)? (exist|not exist|be:|contain:|not contain):$/` - Expect a certain file or directory to (not) exist or (not) contain certain contents.
* `Then /^the contents of the (.+) file should( not)? match (((\/.*\/)|(#.#))([a-z]+)?)$/` - Match file contents against a regex.
* `Then /^(STDOUT|STDERR) should( not)? match (((\/.*\/)|(#.#))([a-z]+)?)$/` - Match STDOUT or STDERR against a regex.
* `Then /^an email should (be sent|not be sent)$/` - Expect an email to be sent (or not).
* `Then the HTTP status code should be :code` - Expect the HTTP status code for visiting `http://localhost:8080`.
