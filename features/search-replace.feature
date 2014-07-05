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
      | Table      | Column | Replacements | Fast Replace |
      | wp_2_posts | guid   | 2            | Yes          |
      | wp_blogs   | path   | 1            | Yes          |

  Scenario Outline: Large guid search/replace where replacement contains search (or not)
    Given a WP install
    And I run `wp option get siteurl`
    And save STDOUT as {SITEURL}
    And I run `wp post generate --count=20`

    When I run `wp search-replace <flags> {SITEURL} <replacement>`
    Then STDOUT should be a table containing rows:
      | Table    | Column | Replacements | Fast Replace |
      | wp_posts | guid   | 22           | No           |

    Examples:
      | replacement          | flags     |
      | {SITEURL}/subdir     |           |
      | http://newdomain.com |           |
      | http://newdomain.com | --dry-run |

  Scenario Outline: Fast and Safe search/replace due to string length and flags
    Given a WP install
    And I run `wp option get siteurl`
    And save STDOUT as {SITEURL}
    And I run `wp search-replace {SITEURL} {SITEURL}/teststring`

    When I run `wp search-replace <flags> {SITEURL}/teststring <replacement>`
    Then STDOUT should be a table containing rows:
      | Table      | Column       | Replacements | Fast Replace |
      | wp_options | option_value | 2            | <fast>       |

    Examples:
      | replacement          | flags  | fast |
      | {SITEURL}/different  |        | No   |
      | {SITEURL}/samelength | --safe | No   |
      | {SITEURL}/samelength |        | Yes  |
