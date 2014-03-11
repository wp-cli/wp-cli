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

    When I run `wp search-replace example.com example.net --export`
    Then STDOUT should contain:
      """
      DROP TABLE IF EXISTS `wp_commentmeta`;
      CREATE TABLE `wp_commentmeta`
      """
    Then STDOUT should contain:
      """
      INSERT INTO `wp_options` (`option_id`, `option_name`, `option_value`, `autoload`) VALUES ('1', 'siteurl', 'http://example.net', 'yes');
      """
    When I run `wp search-replace example.com example.net --skip-columns=option_value --export`
    Then STDOUT should contain:
      """
      INSERT INTO `wp_options` (`option_id`, `option_name`, `option_value`, `autoload`) VALUES ('1', 'siteurl', 'http://example.com', 'yes');
      """

    When I run `wp search-replace foo bar --export | tail -n 1`
    Then STDOUT should not contain:
      """
      Success: Made
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
