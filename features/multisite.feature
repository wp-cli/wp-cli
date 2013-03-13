Feature: Manage a WordPress multisite installation

  Scenario: Install multisite
    Given a WP install

    When I run `wp core install-network`
    Then it should run without errors

    When I run the previous command again
    Then the return code should be 1

  Scenario: Create some blogs
    Given a WP multisite install

    When I run `wp blog create --slug=first --title=First`
    Then it should run without errors
    And STDOUT should not be empty
