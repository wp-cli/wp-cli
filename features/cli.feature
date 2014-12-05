Feature: `wp cli` tasks

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
