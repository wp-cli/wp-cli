Feature: Get specific properties from an alias

  Scenario: Get a specific property from an alias
    Given a config file:
      """
      @test:
        path: /var/www/html
        user: wpcli
      """

    When I run `wp cli alias get @test path`
    Then STDOUT should be:
      """
      /var/www/html
      """

    When I run `wp cli alias get @test user --format=json`
    Then STDOUT should be:
      """
      "wpcli"
      """