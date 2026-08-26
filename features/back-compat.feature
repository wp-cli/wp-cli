Feature: Deprecated command and flag syntaxes still work but warn

  A set of pre-1.0 command and flag syntaxes are rewritten to their modern form
  by the framework for backward compatibility. As of WP-CLI 3.0 they emit a
  deprecation warning naming the modern form, and are scheduled for removal in
  4.0. The rewrite itself keeps working throughout the 3.x cycle.


  Scenario: Retained shorthands are not treated as deprecated
    Given an empty directory

    When I run `wp --version`
    Then STDOUT should contain:
      """
      WP-CLI
      """
    And STDERR should not contain:
      """
      is deprecated
      """
