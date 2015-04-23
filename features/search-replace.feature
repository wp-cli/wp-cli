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
      | Table      | Column | Replacements | Type |
      | wp_2_posts | guid   | 2            | SQL  |
      | wp_blogs   | path   | 1            | SQL  |

  Scenario: Don't run on unregistered tables by default
    Given a WP install
    And I run `wp db query "CREATE TABLE wp_awesome ( id int(11) unsigned NOT NULL AUTO_INCREMENT, awesome_stuff TEXT, PRIMARY KEY (id) ) ENGINE=InnoDB DEFAULT CHARSET=latin1;"`

    When I run `wp search-replace foo bar`
    Then STDOUT should not contain:
      """
      wp_awesome
      """

    When I run `wp search-replace foo bar --all-tables-with-prefix`
    Then STDOUT should contain:
      """
      wp_awesome
      """

  Scenario: Run on unregistered, unprefixed tables with --all-tables flag
    Given a WP install
    And I run `wp db query "CREATE TABLE awesome_table ( id int(11) unsigned NOT NULL AUTO_INCREMENT, awesome_stuff TEXT, PRIMARY KEY (id) ) ENGINE=InnoDB DEFAULT CHARSET=latin1;"`

    When I run `wp search-replace foo bar`
    Then STDOUT should not contain:
      """
      awesome_table
      """

    When I run `wp search-replace foo bar --all-tables`
    Then STDOUT should contain:
      """
      awesome_table
      """

  Scenario: Quiet search/replace
    Given a WP install

    When I run `wp search-replace foo bar --quiet`
    Then STDOUT should be empty

  Scenario Outline: Large guid search/replace where replacement contains search (or not)
    Given a WP install
    And I run `wp option get siteurl`
    And save STDOUT as {SITEURL}
    And I run `wp post generate --count=20`

    When I run `wp search-replace <flags> {SITEURL} <replacement>`
    Then STDOUT should be a table containing rows:
      | Table    | Column | Replacements | Type |
      | wp_posts | guid   | 22           | SQL  |

    Examples:
      | replacement          | flags     |
      | {SITEURL}/subdir     |           |
      | http://newdomain.com |           |
      | http://newdomain.com | --dry-run |

  Scenario Outline: Choose replacement method (PHP or MySQL) given proper flags or data.
    Given a WP install
    And I run `wp option get siteurl`
    And save STDOUT as {SITEURL}
    When I run `wp search-replace <flags> {SITEURL} http://wordpress.org`

    Then STDOUT should be a table containing rows:
      | Table      | Column       | Replacements | Type       |
      | wp_options | option_value | 2            | <serial>   |
      | wp_posts   | post_title   | 0            | <noserial> |

    Examples:
      | flags     | serial | noserial |
      |           | PHP    | SQL      |
      | --precise | PHP    | PHP      |

  Scenario Outline: Ensure search and replace uses PHP (precise) mode when serialized data is found
    Given a WP install
    And I run `wp post create --post_content='<input>' --porcelain`
    And save STDOUT as {CONTROLPOST}
    And I run `wp search-replace --precise foo bar`
    And I run `wp post get {CONTROLPOST} --field=content`
    And save STDOUT as {CONTROL}
    And I run `wp post create --post_content='<input>' --porcelain`
    And save STDOUT as {TESTPOST}
    And I run `wp search-replace foo bar`

    When I run `wp post get {TESTPOST} --field=content`
    Then STDOUT should be:
      """
      {CONTROL}
      """

    Examples:
      | input                                 |
      | a:1:{s:3:"bar";s:3:"foo";}            |
      | O:8:"stdClass":1:{s:1:"a";s:3:"foo";} |
