Feature: Deprecated command and flag syntaxes still work but warn

  A set of pre-1.0 command and flag syntaxes are rewritten to their modern form
  by the framework for backward compatibility. As of WP-CLI 3.0 they emit a
  deprecation warning naming the modern form, and are scheduled for removal in
  4.0. The rewrite itself keeps working throughout the 3.x cycle.

  Scenario: A deprecated top-level command alias warns
    Given an empty directory

    When I try `wp sql`
    Then STDERR should contain:
      """
      The 'wp sql' syntax is deprecated and will be removed in WP-CLI 4.0. Use 'wp db' instead.
      """

  Scenario: A deprecated subcommand syntax warns
    Given an empty directory

    When I try `wp plugin update-all`
    Then STDERR should contain:
      """
      The 'wp plugin update-all' syntax is deprecated and will be removed in WP-CLI 4.0. Use 'wp plugin update --all' instead.
      """

  Scenario: A deprecated flag warns
    Given an empty directory

    When I try `wp post list --ids`
    Then STDERR should contain:
      """
      The '--ids' syntax is deprecated and will be removed in WP-CLI 4.0. Use '--format=ids' instead.
      """

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
