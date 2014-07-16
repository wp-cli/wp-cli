Feature: Do global search/replace

  Scenario: Basic search/replace
    Given a WP install

    When I run `wp search-replace foo bar`
    Then STDOUT should contain:
      """
      guid
      """

    When I run `wp search-replace foo bar --skip-columns=guid`
    Then STDOUT should not contain:
      """
      guid
      """

  Scenario: Multisite search/replace
    Given a WP multisite install
    And I run `wp site create --slug="foo" --title="foo" --email="foo@example.com"`
    And I run `wp search-replace foo bar --network`
    Then STDOUT should be a table containing rows:
      | Table      | Column | Replacements |
      | wp_2_posts | guid   | 2            |
      | wp_blogs   | path   | 1            |

  Scenario Outline: Large guid search/replace where replacement contains search (or not)
    Given a WP install
    And I run `wp option get siteurl`
    And save STDOUT as {SITEURL}
    And I run `wp post generate --count=20`

    When I run `wp search-replace <flags> {SITEURL} <replacement>`
    Then STDOUT should be a table containing rows:
      | Table    | Column | Replacements |
      | wp_posts | guid   | 22           |

    Examples:
      | replacement          | flags     |
      | {SITEURL}/subdir     |           |
      | http://newdomain.com |           |
      | http://newdomain.com | --dry-run |

  Scenario: Identical search and replace terms
    Given a WP install

    When I try `wp search-replace foo foo`
    Then STDERR should be:
      """
      Warning: Use --dry-run for test replacements. Identical <old> and <new> values will not give proper replacement counts.
      """

    When I try `wp search-replace foo foo --dry-run`
    Then STDERR should be:
      """
      Warning: Identical <old> and <new> values will not give proper replacement counts.
      """