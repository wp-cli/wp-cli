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

