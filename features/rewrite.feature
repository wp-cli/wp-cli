Feature: Manage WordPress rewrites

  Scenario: Change site permastructs
    Given a WP install

    When I run `wp rewrite structure /blog/%year%/%monthnum%/%day%/%postname%/ --category-base=section --tag-base=topic`
    And I run `wp option get permalink_structure`
    Then STDOUT should contain:
      """
      /blog/%year%/%monthnum%/%day%/%postname%/
      """

    When I run `wp option get category_base`
    Then STDOUT should contain:
      """
      section
      """

    When I run `wp option get tag_base`
    Then STDOUT should contain:
      """
      topic
      """

    When I run `wp rewrite list --format=csv`
    Then STDOUT should be CSV containing:
      | match            | query                               | source   |
      | blog/[0-9]{4}/[0-9]{1,2}/[0-9]{1,2}/[^/]+/attachment/([^/]+)/trackback/?$ | index.php?attachment=$matches[1]&tb=1 | post |
      | topic/([^/]+)/?$ | index.php?tag=$matches[1]           | post_tag |
      | section/(.+?)/?$ | index.php?category_name=$matches[1] | category |

    When I run `wp rewrite list --match=/topic/apple/ --format=csv --fields=match,query`
    Then STDOUT should be CSV containing:
      | match            | query                               |
      | topic/([^/]+)/?$ | index.php?tag=$matches[1]           |
    And STDOUT should not contain:
      """
      source
      """

  Scenario: Missing permalink_structure
    Given a WP install

    When I run `wp option delete permalink_structure`
    And I try `wp option get permalink_structure`
    Then STDOUT should be empty

    When I try `wp rewrite flush`
    Then STDERR should contain:
      """
      Warning: Rewrite rules are empty, possibly because of a missing permalink_structure option.
      """
    And STDOUT should be empty

    When I run `wp rewrite structure /%year%/%monthnum%/%day%/%postname%/`
    Then I run `wp rewrite flush`
    Then STDOUT should be:
      """
      Success: Rewrite rules flushed.
      """

  Scenario: Generate .htaccess on hard flush with a project config
    Given a WP install
    And a wp-cli.yml file:
      """
      apache_modules: [mod_rewrite]
      """

    When I run `wp rewrite structure /%year%/%monthnum%/%day%/%postname%/ --hard`
    Then the .htaccess file should exist
    And the return code should be 0

  Scenario: Generate .htaccess on hard flush with a global config
    Given a WP install
    And a config.yml file:
      """
      apache_modules: [mod_rewrite]
      """

    When I run `WP_CLI_CONFIG_PATH=config.yml wp rewrite structure /%year%/%monthnum%/%day%/%postname%/ --hard`
    Then the .htaccess file should exist
    And the return code should be 0

  Scenario: Error when trying to generate .htaccess on a multisite install
    Given a WP multisite install
    And a wp-cli.yml file:
      """
      apache_modules: [mod_rewrite]
      """

    When I try `wp rewrite flush --hard`
    Then STDERR should be:
      """
      Warning: WordPress can't generate .htaccess file for a multisite install.
      """
    And the return code should be 0

    When I try `wp rewrite structure /%year%/%monthnum%/%day%/%postname%/ --hard`
    Then STDERR should contain:
      """
      Warning: WordPress can't generate .htaccess file for a multisite install.
      """
    And the return code should be 0
