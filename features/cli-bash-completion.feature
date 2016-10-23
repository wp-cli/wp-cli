Feature: `wp cli completions` tasks

  Scenario: Bash Completion
    Given an empty directory
    And a wp-cli.yml file:
      """
      @example:
        ssh: example.com
      """

    When I run `wp cli completions --line="wp " --point=100`
    Then STDOUT should contain:
      """
      @example
      """
    And STDOUT should contain:
      """
      plugin
      """
    And STDOUT should contain:
      """
      server
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp @e" --point=100`
    Then STDOUT should contain:
      """
      @example
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp @example " --point=100`
    Then STDOUT should not contain:
      """
      @example
      """
    And STDOUT should contain:
      """
      core
      """
    And STDOUT should contain:
      """
      eval
      """
    And STDERR should be empty
    And the return code should be 0
