Feature: Manage WordPress theme mods

  Scenario: Getting theme mods
    Given a WP install

    When I run `wp theme mod get --all`
    Then STDOUT should be a table containing rows:
      | key  | value   |

    When I try `wp theme mod get`
    Then STDERR should contain:
      """
      You must specify at least one mod or use --all.
      """

    When I run `wp theme mod set background_color 123456`
    And I run `wp theme mod get --all`
    Then STDOUT should be a table containing rows:
      | key               | value    |
      | background_color  | 123456   |

    When I run `wp theme mod get background_color --field=value`
    Then STDOUT should be:
      """
      123456
      """

    When I run `wp theme mod set background_color 123456`
    And I run `wp theme mod get background_color header_textcolor`
    Then STDOUT should be a table containing rows:
      | key               | value    |
      | background_color  | 123456   |
      | header_textcolor  |          |

  Scenario: Setting theme mods
    Given a WP install

    When I run `wp theme mod set background_color 123456`
    Then STDOUT should be:
      """
      Success: Theme mod background_color set to 123456.
      """

  Scenario: Removing theme mods
    Given a WP install

    When I run `wp theme mod remove --all`
    Then STDOUT should be:
      """
      Success: Theme mods removed.
      """

    When I try `wp theme mod remove`
    Then STDERR should contain:
      """
      You must specify at least one mod or use --all.
      """

    When I run `wp theme mod remove background_color`
    Then STDOUT should be:
      """
      Success: 1 mod removed.
      """

    When I run `wp theme mod remove background_color header_textcolor`
    Then STDOUT should be:
      """
      Success: 2 mods removed.
      """
