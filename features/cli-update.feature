Feature: CLI Update

  Scenario: Errors when not using a Phar

    When I try `wp cli update`

    Then STDOUT should be empty
    Then STDERR should contain:
      """
      Error: You can only self-update Phar files.
      """
