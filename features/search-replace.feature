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

  Scenario: Large guid search/replace where replacement contains search
    Given a WP install

    When I run `wp option get siteurl`
   
    And save STDOUT as {SITEURL}

    And I run `wp post generate --count=1000`

    And I run `wp search-replace {SITEURL} {SITEURL}/subdir`
    Then STDOUT should be a table containing rows:
    """
    Table	Column	Replacements
    wp_posts	guid	1002
    """
  Scenario: Large guid search/replace where replacement does not contain search
    Given a WP install

    When I run `wp option get siteurl`

    And save STDOUT as {SITEURL}

    And I run `wp post generate --count=1000`

    And I run `wp search-replace {SITEURL} http://newdomain.com`
    Then STDOUT should be a table containing rows:
    """
    Table	Column	Replacements
    wp_posts	guid	1002
    """
