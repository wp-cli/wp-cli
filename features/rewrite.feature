Feature: Manage WordPress rewrites

  Background:
    Given a WP install

  Scenario: Change site permastructs
    When I run `wp rewrite structure /blog/%year%/%monthnum%/%day%/%postname%/ --category-base=section --tag-base=topic`

   When I run `wp option get permalink_structure`
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
