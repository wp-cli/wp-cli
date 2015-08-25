Feature: `wp cli` tasks

  Scenario: Ability to set a custom version when building
    Given an empty directory
    And save the {SRC_DIR}/VERSION file as {TRUE_VERSION}
    And a new Phar with version "1.2.3"

    When I run `{PHAR_PATH} cli version`
    Then STDOUT should be:
    """
    WP-CLI 1.2.3
    """
    And the {SRC_DIR}/VERSION file should be:
    """
    {TRUE_VERSION}
    """

  @github-api
  Scenario: Check for updates
    Given an empty directory
    And a new Phar with version "0.0.0"

    When I run `{PHAR_PATH} cli check-update`
    Then STDOUT should contain:
    """
    package_url
    """
    And STDERR should be empty

  @github-api
  Scenario: Do WP-CLI Update
    Given an empty directory
    And a new Phar with version "0.0.0"

    When I run `{PHAR_PATH} cli update --yes`
    Then STDOUT should contain:
    """
    Success:
    """
    And STDERR should be empty
    And the return code should be 0

  @github-api
  Scenario: Patch update from 0.14.0 to 0.14.1
    Given an empty directory
    And a new Phar with version "0.14.0"

    When I run `{PHAR_PATH} cli update --patch --yes`
    Then STDOUT should contain:
    """
    Success: Updated WP-CLI to 0.14.1
    """
    And STDERR should be empty
    And the return code should be 0

  @github-api
  Scenario: Not a patch update from 0.14.0
    Given an empty directory
    And a new Phar with version "0.14.0"

    When I run `{PHAR_PATH} cli update --no-patch --yes`
    Then STDOUT should contain:
    """
    Success:
    """
    And STDOUT should not contain:
    """
    0.14.1
    """
    And STDERR should be empty
    And the return code should be 0

  Scenario: Install WP-CLI nightly
    Given an empty directory
    And a new Phar with version "0.14.0"

    When I run `{PHAR_PATH} cli update --nightly --yes`
    Then STDOUT should contain:
      """
      Success: Updated WP-CLI to the latest nightly release
      """

    And STDERR should be empty
    And the return code should be 0

  @github-api
  Scenario: Dump the list of global parameters with values
    Given a WP install

    When I run `wp cli param-dump --with-values | grep -o '"current":' | uniq -c`
    Then STDOUT should be:
      """
           15 "current":
      """
    And STDERR should be empty
    And the return code should be 0
