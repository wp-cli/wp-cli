Feature: Manage WordPress theme mods

  Scenario: Getting theme mods
    Given a WP install

    When I run `wp theme mod get --all`
    Then STDOUT should be a table containing rows:
      | key  | value   |

  Scenario: Setting theme mods
    Given a WP install

    When I run `wp theme mod set background_color 123456`
    Then STDOUT should be:
      """
      Success: Theme mod background_color set to 123456
      """

    When I run `wp theme mod set background_color 123456`
    And I run `wp theme mod get --all`
    Then STDOUT should be a table containing rows:
      | key               | value    |
      | background_color  | 123456   |
