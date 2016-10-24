Feature: `wp cli completions` tasks

  Scenario: Bash Completion without wp-cli.yml
    Given an empty directory

    When I run `wp cli completions --line="wp " --point=100`
    Then STDOUT should contain:
      """
      plugin
      """
    And STDOUT should contain:
      """
      server
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp core " --point=100`
    Then STDOUT should contain:
      """
      install
      """
    And STDOUT should contain:
      """
      update
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp help " --point=100`
    Then STDOUT should contain:
      """
      rewrite
      """
    And STDOUT should contain:
      """
      media
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp help core language " --point=100`
    Then STDOUT should contain:
      """
      install
      """
    And STDOUT should contain:
      """
      update
      """
    And STDERR should be empty
    And the return code should be 0

  Scenario: Bash Completion with SSH aliases
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

    When I run `wp cli completions --line="wp @example plugin " --point=100`
    Then STDOUT should contain:
      """
      list
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp help core language " --point=100`
    Then STDOUT should contain:
      """
      install
      """
    And STDOUT should contain:
      """
      update
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp help " --point=100`
    Then STDOUT should not contain:
      """
      @example
      """
    And STDOUT should contain:
      """
      post-type
      """
    And STDERR should be empty
    And the return code should be 0
