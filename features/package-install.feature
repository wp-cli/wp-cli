Feature: Install WP-CLI packages

  Scenario: Install a package with an http package index url in package composer.json
    Given an empty directory
    And a composer.json file:
      """
      {
        "repositories": {
          "test" : {
            "type": "path",
            "url": "./dummy-package/"
          },
          "wp-cli": {
            "type": "composer",
            "url": "http://wp-cli.org/package-index/"
          }
        }
      }
      """
    And a dummy-package/composer.json file:
	  """
	  {
	    "name": "wp-cli/restful",
	    "description": "This is a dummy package we will install instead of actually installing the real package. This prevents the test from hanging indefinitely for some reason, even though it passes. The 'name' must match a real package as it is checked against the package index."
	  }
	  """
    When I run `WP_CLI_PACKAGES_DIR=. wp package install wp-cli/restful --debug`
    Then STDOUT should contain:
	  """
	  Updating package index repository url...
	  """
    And STDOUT should contain:
	  """
	  Success: Package installed
	  """
    And the composer.json file should contain:
      """
      "url": "https://wp-cli.org/package-index/"
      """
    And the composer.json file should not contain:
      """
      "url": "http://wp-cli.org/package-index/"
      """

  Scenario: Install a package with 'wp-cli/wp-cli' as a dependency
    Given a WP install

    When I run `wp package install sinebridge/wp-cli-about:v1.0.1`
    Then STDOUT should contain:
      """
      Success: Package installed
      """
    And STDOUT should not contain:
      """
      requires wp-cli/wp-cli
      """

    When I run `wp about`
    Then STDOUT should contain:
      """
      Site Information
      """

  @require-php-5.6
  Scenario: Install a package with a dependency
    Given an empty directory

    When I run `wp package path`
    Then save STDOUT as {PACKAGE_PATH}

    When I run `wp package install trendwerk/faker`
    Then STDOUT should contain:
      """
      Error: trendwerk/faker dev-master requires nelmio/alice
      """
    And STDOUT should contain:
      """
      Success: Package installed
      """
    And the {PACKAGE_PATH}/vendor/trendwerk directory should contain:
      """
      faker
      """
    And the {PACKAGE_PATH}/vendor/nelmio directory should contain:
      """
      alice
      """

    When I run `wp package list`
    Then STDOUT should contain:
      """
      trendwerk/faker
      """

    When I run `wp package uninstall trendwerk/faker`
    Then STDOUT should contain:
      """
      Removing require statement
      """
    And STDOUT should contain:
      """
      Success: Uninstalled package.
      """
    And the {PACKAGE_PATH}/vendor directory should not contain:
      """
      trendwerk
      """
    And the {PACKAGE_PATH}/vendor directory should not contain:
      """
      alice
      """

    When I run `wp package list`
    Then STDOUT should not contain:
      """
      trendwerk/faker
      """
