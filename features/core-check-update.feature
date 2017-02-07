Feature: Check for more recent versions

  Scenario: Check for update via Version Check API
    Given a WP install

    When I run `wp core download --version=4.4 --force`
    Then STDOUT should not be empty

    When I run `wp core check-update`
    Then STDOUT should be a table containing rows:
      | version | update_type | package_url                                                  |
      | 4.7.2   | major       | https://downloads.wordpress.org/release/wordpress-4.7.2.zip    |
      | 4.4.7   | minor       | https://downloads.wordpress.org/release/wordpress-4.4.7-partial-0.zip |

    When I run `wp core check-update --format=count`
    Then STDOUT should be:
      """
      2
      """

    When I run `wp core check-update --major`
    Then STDOUT should be a table containing rows:
      | version | update_type | package_url                                                  |
      | 4.7.2   | major       | https://downloads.wordpress.org/release/wordpress-4.7.2.zip  |

    When I run `wp core check-update --major --format=count`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp core check-update --minor`
    Then STDOUT should be a table containing rows:
      | version | update_type | package_url                                |
      | 4.4.7   | minor       | https://downloads.wordpress.org/release/wordpress-4.4.7-partial-0.zip |

    When I run `wp core check-update --minor --format=count`
    Then STDOUT should be:
      """
      1
      """

  @less-than-php-7
  Scenario: No minor updates for an unlocalized WordPress release
    Given a WP install

    When I run `wp core download --version=4.0 --locale=es_ES --force`
    Then STDOUT should contain:
      """
      Success: WordPress downloaded.
      """

    When I run `wp core check-update --minor`
    Then STDOUT should be a table containing rows:
      | version | update_type | package_url                                        |
      | 4.0.15  | minor       | https://downloads.wordpress.org/release/wordpress-4.0.15-partial-0.zip |
