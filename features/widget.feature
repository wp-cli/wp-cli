Feature: Manage widgets in WordPress sidebar

  Background:
    Given a WP install
    When I run `wp theme install p2 --activate`
    Then STDOUT should not be empty

  Scenario: Widget CRUD
    When I run `wp widget list sidebar-1 --fields=name,id,position`
    Then STDOUT should be a table containing rows:
      | name            | id                | position |
      | search          | search-2          | 1        |
      | recent-posts    | recent-posts-2    | 2        |
      | recent-comments | recent-comments-2 | 3        |
      | archives        | archives-2        | 4        |
      | categories      | categories-2      | 5        |
      | meta            | meta-2            | 6        |

    When I run `wp widget move recent-comments-2 --position=2`
    Then STDOUT should not be empty

    When I run `wp widget list sidebar-1 --fields=name,id,position`
    Then STDOUT should be a table containing rows:
      | name            | id                | position |
      | search          | search-2          | 1        |
      | recent-comments | recent-comments-2 | 2        |
      | recent-posts    | recent-posts-2    | 3        |
      | archives        | archives-2        | 4        |
      | categories      | categories-2      | 5        |
      | meta            | meta-2            | 6        |

    When I run `wp widget move recent-comments-2 --sidebar-id=wp_inactive_widgets`
    Then STDOUT should not be empty

    When I run `wp widget deactivate meta-2`
    Then STDOUT should be:
      """
      Success: Deactivated 1 of 1 widgets.
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp widget list sidebar-1 --fields=name,id,position`
    Then STDOUT should be a table containing rows:
      | name            | id                | position |
      | search          | search-2          | 1        |
      | recent-posts    | recent-posts-2    | 2        |
      | archives        | archives-2        | 3        |
      | categories      | categories-2      | 4        |

    When I run `wp widget list wp_inactive_widgets --fields=name,id,position`
    Then STDOUT should be a table containing rows:
      | name            | id                | position |
      | meta            | meta-2            | 1        |
      | recent-comments | recent-comments-2 | 2        |

    When I run `wp widget delete archives-2 recent-posts-2`
    Then STDOUT should be:
      """
      Success: Deleted 2 of 2 widgets.
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp widget list sidebar-1 --fields=name,id,position`
    Then STDOUT should be a table containing rows:
      | name            | id                | position |
      | search          | search-2          | 1        |
      | categories      | categories-2      | 2        |

    When I run `wp widget add calendar sidebar-1 2`
    Then STDOUT should not be empty

    When I run `wp widget list sidebar-1 --fields=name,id,position`
    Then STDOUT should be a table containing rows:
      | name            | id                | position |
      | search          | search-2          | 1        |
      | calendar        | calendar-1        | 2        |
      | categories      | categories-2      | 3        |

    When I run `wp widget list sidebar-1 --format=ids`
    Then STDOUT should be:
      """
      search-2 calendar-1 categories-2
      """

    When I run `wp widget update calendar-1 --title="Calendar"`
    Then STDOUT should not be empty

    When I run `wp widget list sidebar-1 --fields=name,position,options`
    Then STDOUT should be a table containing rows:
      | name            | position | options               |
      | calendar        | 2        | {"title":"Calendar"}  |

  Scenario: Validate sidebar widgets
    When I try `wp widget update calendar-999`
    Then STDERR should be:
      """
      Error: Widget doesn't exist.
      """
    And the return code should be 1

    When I try `wp widget move calendar-999`
    Then STDERR should be:
      """
      Error: Widget doesn't exist.
      """
    And the return code should be 1

  Scenario: Return code is 0 when all widgets exist, deactivation
    When I run `wp widget deactivate recent-posts-2`
    Then STDOUT should be:
      """
      Success: Deactivated 1 of 1 widgets.
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp widget deactivate search-2 archives-2`
    Then STDOUT should be:
      """
      Success: Deactivated 2 of 2 widgets.
      """
    And STDERR should be empty
    And the return code should be 0

  Scenario: Return code is 0 when all widgets exist, deletion
    When I run `wp widget delete recent-posts-2`
    Then STDOUT should be:
      """
      Success: Deleted 1 of 1 widgets.
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp widget delete search-2 archives-2`
    Then STDOUT should be:
      """
      Success: Deleted 2 of 2 widgets.
      """
    And STDERR should be empty
    And the return code should be 0

  Scenario: Return code is 1 when 1 or more widgets doesn't exist, deactivation
    When I try `wp widget deactivate calendar-999`
    Then STDERR should be:
      """
      Warning: Widget 'calendar-999' doesn't exist.
      Error: No widgets deactivated.
      """
    And the return code should be 1

    When I try `wp widget deactivate recent-posts-2 calendar-999`
    Then STDERR should be:
      """
      Warning: Widget 'calendar-999' doesn't exist.
      Error: Only deactivated 1 of 2 widgets.
      """
    And the return code should be 1

  Scenario: Return code is 1 when 1 or more widgets doesn't exist, deletion
    When I try `wp widget delete calendar-999`
    Then STDERR should be:
      """
      Warning: Widget 'calendar-999' doesn't exist.
      Error: No widgets deleted.
      """
    And the return code should be 1

    When I try `wp widget delete recent-posts-2 calendar-999`
    Then STDERR should be:
      """
      Warning: Widget 'calendar-999' doesn't exist.
      Error: Only deleted 1 of 2 widgets.
      """
    And the return code should be 1
