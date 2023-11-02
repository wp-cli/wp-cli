Feature: CLI Update

  Scenario: Errors when not using a Phar

    When I try `wp cli update`

    Then STDOUT should be empty
    Then STDERR should contain:
      """
      Error: You can only self-update Phar files.
      """

  @github-api
  Scenario: Do WP-CLI Update
    Given an empty directory
    And a new Phar with version "0.0.0"

    When I run `{PHAR_PATH} --info`
    Then STDOUT should contain:
      """
      WP-CLI version
      """
    And STDOUT should contain:
      """
      0.0.0
      """

    When I run `{PHAR_PATH} cli update --yes`
    Then STDOUT should contain:
      """
      md5 hash verified:
      """
    And STDOUT should contain:
    """
    Success:
    """
    And STDERR should be empty
    And the return code should be 0

    When I run `{PHAR_PATH} --info`
    Then STDOUT should contain:
      """
      WP-CLI version
      """
    And STDOUT should not contain:
      """
      0.0.0
      """

    When I run `{PHAR_PATH} cli update`
    Then STDOUT should be:
      """
      Success: WP-CLI is at the latest version.
      """
