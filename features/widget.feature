Feature: Manage widgets in WordPress sidebar

  Scenario: Widget CRUD
    Given a WP install

    When I run `wp theme install p2 --activate`
    Then STDOUT should not be empty

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
    Then STDOUT should not be empty

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
    Then STDOUT should not be empty

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
