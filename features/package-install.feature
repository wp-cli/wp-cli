Feature: Install WP-CLI packages

  Scenario: Install a package with an http package index url in package composer.json
    Given an empty directory
    And a composer.json file:
      """
      {
        "repositories": {
          "wp-cli": {
            "type": "composer",
            "url": "http://wp-cli.org/package-index/"
          }
        }
      }
      """
    When I run `rm -rf /tmp/wp-cli-package-install-test/ && mkdir /tmp/wp-cli-package-install-test/ && mv composer.json /tmp/wp-cli-package-install-test/`
    Then the /tmp/wp-cli-package-install-test/composer.json file should exist
    When I run `WP_CLI_PACKAGES_DIR=/tmp/wp-cli-package-install-test/ wp --info`
    Then STDOUT should contain:
      """
      WP-CLI packages dir:	/tmp/wp-cli-package-install-test/
      """
    When I run `WP_CLI_PACKAGES_DIR=/tmp/wp-cli-package-install-test/ wp package install runcommand/hook`
    Then the /tmp/wp-cli-package-install-test/composer.json file should contain:
      """
      "url": "https://wp-cli.org/package-index/"
      """
    And the /tmp/wp-cli-package-install-test/composer.json file should not contain:
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
