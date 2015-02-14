@github-api
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

  Scenario: Check for updates
    Given an empty directory
    And a new Phar with version "0.0.0"

    When I run `{PHAR_PATH} cli check-update`
    Then STDOUT should contain:
    """
    package_url
    """
    And STDERR should be empty

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
