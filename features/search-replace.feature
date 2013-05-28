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

  Scenario: Small guid search/replace
    Given a WP install

    When I run `wp option get siteurl`
   
    And save STDOUT as {SITEURL}

    And I run `wp post generate --count=100`

    And I run `wp search-replace {SITEURL} testreplacement`
    Then STDOUT should be a table containing rows:
    """
    Table	Column	Replacements
    wp_posts	guid	102
    """
  Scenario: Large guid search/replace
    Given a WP install

    When I run `wp option get siteurl`

    And save STDOUT as {SITEURL}

    And I run `wp post generate --count=1200`

    And I run `wp search-replace {SITEURL} testreplacement`
    Then STDOUT should be a table containing rows:
    """
    Table	Column	Replacements
    wp_posts	guid	1202
    """
