Feature: Manage sites in a multisite installation

  Scenario: Create a site
    Given a WP multisite install
    
    When I try `wp site create --slug=first --network_id=1000`
    Then STDERR should contain:
      """
      Network with id 1000 does not exist.
      """

  Scenario: Delete a site by id
    Given a WP multisite install

    When I run `wp site create --slug=first --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {SITE_ID}

    When I run `wp site list --fields=blog_id,url`
    Then STDOUT should be a table containing rows:
      | blog_id | url                |
      | 1       | example.com/       |
      | 2       | example.com/first/ |

    When I run `wp site list --field=url`
    Then STDOUT should be:
      """
      example.com/
      example.com/first/
      """

    When I run `wp site delete {SITE_ID} --yes`
    Then STDOUT should not be empty

    When I try the previous command again
    Then the return code should be 1

  Scenario: Filter site list
    Given a WP multisite install

    When I run `wp site create --slug=first --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {SITE_ID}

    When I run `wp site list --fields=blog_id,url`
    Then STDOUT should be a table containing rows:
      | blog_id | url                |
      | 1       | example.com/       |
      | 2       | example.com/first/ |

    When I run `wp site list --field=url --blog_id=2`
    Then STDOUT should be:
      """
      example.com/first/
      """

  Scenario: Delete a site by slug
    Given a WP multisite install

    When I run `wp site create --slug=first`
    Then STDOUT should not be empty

    When I run `wp site delete --slug=first --yes`
    Then STDOUT should not be empty

    When I try the previous command again
    Then the return code should be 1

  Scenario: Empty a site
    Given a WP install

    When I try `wp site url 1`
    Then STDERR should be:
      """
      Error: This is not a multisite install.
      """

    When I run `wp post create --post_title='Test post' --post_content='Test content.' --porcelain`
    Then STDOUT should not be empty

    When I run `wp term create post_tag 'Test term' --slug=test --description='This is a test term'`
    Then STDOUT should not be empty

    When I run `wp site empty --yes`
    Then STDOUT should not be empty

    When I run `wp post list --format=ids`
    Then STDOUT should be empty

    When I run `wp term list post_tag --format=ids`
    Then STDOUT should be empty

  Scenario: Get site info
    Given a WP multisite install
   
    When I run `wp site create --slug=first --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {SITE_ID}
 
    When I run `wp site url {SITE_ID}`
    Then STDOUT should be:
      """
      http://example.com/first
      """

  Scenario: Archive/unarchive a site
    Given a WP multisite install
    And I run `wp site create --slug=first --porcelain`
    And save STDOUT as {FIRST_SITE}
    And I run `wp site create --slug=second --porcelain`
    And save STDOUT as {SECOND_SITE}

    When I run `wp site archive {FIRST_SITE}`
    Then STDOUT should be:
      """
      Success: Site {FIRST_SITE} archived.
      """

    When I run `wp site list --fields=blog_id,archived`
    Then STDOUT should be a table containing rows:
      | blog_id      | archived |
      | {FIRST_SITE} | 1        |

    When I run `wp site archive {FIRST_SITE} {SECOND_SITE}`
    Then STDERR should be:
      """
      Warning: Site {FIRST_SITE} already archived.
      """
    And STDOUT should be:
      """
      Success: Site {SECOND_SITE} archived.
      """

    When I run `wp site list --fields=blog_id,archived`
    Then STDOUT should be a table containing rows:
      | blog_id      | archived |
      | {FIRST_SITE} | 1        |

    When I run `wp site unarchive {FIRST_SITE}`
    Then STDOUT should be:
      """
      Success: Site {FIRST_SITE} unarchived.
      """

    When I run `wp site list --fields=blog_id,archived`
    Then STDOUT should be a table containing rows:
      | blog_id      | archived |
      | {FIRST_SITE} | 0        |

    When I run `wp site archive 1`
    Then STDERR should be:
      """
      Warning: You are not allowed to change the main site.
      """

  Scenario: Activate/deactivate a site
    Given a WP multisite install
    And I run `wp site create --slug=first --porcelain`
    And save STDOUT as {FIRST_SITE}
    And I run `wp site create --slug=second --porcelain`
    And save STDOUT as {SECOND_SITE}

    When I run `wp site deactivate {FIRST_SITE}`
    Then STDOUT should be:
      """
      Success: Site {FIRST_SITE} deactivated.
      """

    When I run `wp site list --fields=blog_id,deleted`
    Then STDOUT should be a table containing rows:
      | blog_id      | deleted |
      | {FIRST_SITE} | 1       |

    When I run `wp site deactivate {FIRST_SITE} {SECOND_SITE}`
    Then STDERR should be:
      """
      Warning: Site {FIRST_SITE} already deactivated.
      """
    And STDOUT should be:
      """
      Success: Site {SECOND_SITE} deactivated.
      """

    When I run `wp site list --fields=blog_id,deleted`
    Then STDOUT should be a table containing rows:
      | blog_id      | deleted |
      | {FIRST_SITE} | 1       |

    When I run `wp site activate {FIRST_SITE}`
    Then STDOUT should be:
      """
      Success: Site {FIRST_SITE} activated.
      """

    When I run `wp site list --fields=blog_id,deleted`
    Then STDOUT should be a table containing rows:
      | blog_id      | deleted |
      | {FIRST_SITE} | 0       |

    When I run `wp site deactivate 1`
    Then STDERR should be:
      """
      Warning: You are not allowed to change the main site.
      """

  Scenario: Mark/remove a site from spam
    Given a WP multisite install
    And I run `wp site create --slug=first --porcelain`
    And save STDOUT as {FIRST_SITE}
    And I run `wp site create --slug=second --porcelain`
    And save STDOUT as {SECOND_SITE}

    When I run `wp site spam {FIRST_SITE}`
    Then STDOUT should be:
      """
      Success: Site {FIRST_SITE} marked as spam.
      """

    When I run `wp site list --fields=blog_id,spam`
    Then STDOUT should be a table containing rows:
      | blog_id      | spam |
      | {FIRST_SITE} | 1    |

    When I run `wp site spam {FIRST_SITE} {SECOND_SITE}`
    Then STDERR should be:
      """
      Warning: Site {FIRST_SITE} already marked as spam.
      """
    And STDOUT should be:
      """
      Success: Site {SECOND_SITE} marked as spam.
      """

    When I run `wp site list --fields=blog_id,spam`
    Then STDOUT should be a table containing rows:
      | blog_id      | spam |
      | {FIRST_SITE} | 1    |

    When I run `wp site unspam {FIRST_SITE}`
    Then STDOUT should be:
      """
      Success: Site {FIRST_SITE} removed from spam.
      """

    When I run `wp site list --fields=blog_id,spam`
    Then STDOUT should be a table containing rows:
      | blog_id      | spam |
      | {FIRST_SITE} | 0    |

    When I run `wp site spam 1`
    Then STDERR should be:
      """
      Warning: You are not allowed to change the main site.
      """
