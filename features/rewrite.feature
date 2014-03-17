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
      | blog/([0-9]{4})/([0-9]{1,2})/([0-9]{1,2})/([^/]+)(/[0-9]+)?/?$ | index.php?year=$matches[1]&monthnum=$matches[2]&day=$matches[3]&name=$matches[4]&page=$matches[5] | post |
      | topic/([^/]+)/?$ | index.php?tag=$matches[1]           | post_tag |
      | section/(.+?)/?$ | index.php?category_name=$matches[1] | category |

    When I run `wp rewrite list --match=/topic/apple/ --format=csv`
    Then STDOUT should be CSV containing:
      | match            | query                               | source   |
      | topic/([^/]+)/?$ | index.php?tag=$matches[1]           | post_tag |

  Scenario: Generate .htaccess on hard flush
    Given a WP install
    And a wp-cli.yml file:
      """
      apache_modules: [mod_rewrite]
      """

    When I run `wp rewrite structure /%year%/%monthnum%/%day%/%postname%/`
    And I run `wp rewrite flush --hard`
    Then the .htaccess file should exist
