Feature: Reset WordPress sidebars

  Scenario: Reset sidebar
    Given a WP install

    When I run `wp theme install twentytwelve --activate`
    Then STDOUT should not be empty

    When I run `wp widget list sidebar-1 --format=count`
    Then STDOUT should be:
      """
      6
      """

    When I run `wp widget reset sidebar-1`
    And I run `wp widget list sidebar-1 --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I try `wp widget reset`
    Then STDERR should be:
      """
      Error: Please specify one or more sidebars, or use --all.
      """

    When I try `wp widget reset sidebar-1`
    Then STDERR should be:
      """
      Warning: Sidebar 'sidebar-1' is already empty.
      """
    And STDOUT should be:
      """
      Success: Sidebar already reset.
      """
    And the return code should be 0

    When I try `wp widget reset non-existing-sidebar-id`
    Then STDERR should be:
      """
      Warning: Invalid sidebar: non-existing-sidebar-id
      Error: No sidebars reset.
      """
    And the return code should be 1

    When I run `wp widget add calendar sidebar-1 --title="Calendar"`
    Then STDOUT should not be empty

    When I run `wp widget list sidebar-1 --format=count`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp widget add search sidebar-2 --title="Quick Search"`
    Then STDOUT should not be empty

    When I run `wp widget list sidebar-2 --format=count`
    Then STDOUT should be:
      """
      1
      """

    When I try `wp widget reset sidebar-1 sidebar-2 non-existing-sidebar-id`
    Then STDERR should be:
      """
      Warning: Invalid sidebar: non-existing-sidebar-id
      Error: Only reset 2 of 3 sidebars.
      """
    And the return code should be 1

    When I run `wp widget list sidebar-1 --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp widget list sidebar-2 --format=count`
    Then STDOUT should be:
      """
      0
      """

  Scenario: Reset all sidebars
    Given a WP install

    When I run `wp theme install twentytwelve --activate`
    Then STDOUT should not be empty

    When I run `wp widget add calendar sidebar-1 --title="Calendar"`
    Then STDOUT should not be empty
    When I run `wp widget add search sidebar-2 --title="Quick Search"`
    Then STDOUT should not be empty
    When I run `wp widget add text sidebar-3 --title="Text"`
    Then STDOUT should not be empty

    When I run `wp widget reset --all`
    Then STDOUT should be:
      """
      Sidebar 'sidebar-1' reset.
      Sidebar 'sidebar-2' reset.
      Sidebar 'sidebar-3' reset.
      Success: Reset 3 of 3 sidebars.
      """
    And the return code should be 0

    When I run `wp widget list sidebar-1 --format=count`
    Then STDOUT should be:
      """
      0
      """
    And I run `wp widget list sidebar-2 --format=count`
    Then STDOUT should be:
      """
      0
      """
    And I run `wp widget list sidebar-3 --format=count`
    Then STDOUT should be:
      """
      0
      """
    When I run `wp widget list wp_inactive_widgets --format=ids`
    Then STDOUT should be:
      """
      text-1 search-3 meta-2 categories-2 archives-2 recent-comments-2 recent-posts-2 search-2 calendar-1
      """

  Scenario: Testing movement of widgets while reset
    Given a WP install

    When I run `wp theme install twentytwelve --activate`
    Then STDOUT should not be empty

    When I run `wp widget add calendar sidebar-2 --title="Calendar"`
    Then STDOUT should not be empty
    And I run `wp widget add search sidebar-2 --title="Quick Search"`
    Then STDOUT should not be empty

    When I run `wp widget list sidebar-2 --format=ids`
    Then STDOUT should be:
      """
      search-3 calendar-1
      """
    When I run `wp widget list wp_inactive_widgets --format=ids`
    Then STDOUT should be empty

    When I run `wp widget reset sidebar-2`
    And I run `wp widget list sidebar-2 --format=ids`
    Then STDOUT should be empty
    And I run `wp widget list wp_inactive_widgets --format=ids`
    Then STDOUT should be:
      """
      calendar-1 search-3
      """
