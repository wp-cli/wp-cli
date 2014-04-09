Feature: Manage widgets in WordPress sidebar

  Scenario: Widget CRUD
    Given a WP install

    When I run `wp theme install p2 --activate`
    Then STDOUT should not be empty

    When I run `wp sidebar widget list sidebar-1 --fields=name,position`
    Then STDOUT should be a table containing rows:
      | name            | position |
      | search          | 1        |
      | recent-posts    | 2        |
      | recent-comments | 3        |
      | archives        | 4        |
      | categories      | 5        |
      | meta            | 6        |

    When I run `wp sidebar widget move sidebar-1 recent-comments 3 2`
    Then STDOUT should not be empty

    When I run `wp sidebar widget list sidebar-1 --fields=name,position`
    Then STDOUT should be a table containing rows:
      | name            | position |
      | search          | 1        |
      | recent-comments | 2        |
      | recent-posts    | 3        |
      | archives        | 4        |
      | categories      | 5        |
      | meta            | 6        |

    When I run `wp sidebar widget move sidebar-1 recent-comments 2 5`
    Then STDOUT should not be empty

    When I run `wp sidebar widget list sidebar-1 --fields=name,position`
    Then STDOUT should be a table containing rows:
      | name            | position |
      | search          | 1        |
      | recent-posts    | 2        |
      | archives        | 3        |
      | categories      | 4        |
      | recent-comments | 5        |
      | meta            | 6        |

    When I run `wp sidebar widget remove sidebar-1 recent-comments 5`
    Then STDOUT should not be empty

    When I run `wp sidebar widget list sidebar-1 --fields=name,position`
    Then STDOUT should be a table containing rows:
      | name            | position |
      | search          | 1        |
      | recent-posts    | 2        |
      | archives        | 3        |
      | categories      | 4        |
      | meta            | 5        |

    When I run `wp sidebar widget add sidebar-1 calendar 2 --title="Calendar"`
    Then STDOUT should not be empty

    When I run `wp sidebar widget list sidebar-1 --fields=name,position`
    Then STDOUT should be a table containing rows:
      | name            | position |
      | search          | 1        |
      | calendar        | 2        |
      | recent-posts    | 3        |
      | archives        | 4        |
      | categories      | 5        |
      | meta            | 6        |
