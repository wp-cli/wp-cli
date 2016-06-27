Feature: Manage WordPress sidebars

  Scenario: Reset sidebar
    Given a WP install

    When I run `wp theme install p2 --activate`
    Then STDOUT should not be empty

    When I run `wp widget list sidebar-1 --format=count`
    Then STDOUT should be:
    """
    6
    """

    When I run `wp sidebar reset sidebar-1`
    And I run `wp widget list sidebar-1 --format=count`
    Then STDOUT should be:
    """
    0
    """

    When I try `wp sidebar reset sidebar-1`
    Then STDERR should be:
    """
    Warning: 'sidebar-1' is already empty.
    """

    When I try `wp sidebar reset sidebar-non-existing`
    Then STDERR should be:
    """
    Error: Invalid sidebar.
    """

  Scenario: Reset all sidebars
    Given a WP install

    When I run `wp theme install twentysixteen --activate`
    Then STDOUT should not be empty

    When I run `wp widget add calendar sidebar-1 --title="Calendar"`
    Then STDOUT should not be empty
    And I run `wp widget list sidebar-1 --format=count`
    Then STDOUT should be:
    """
    7
    """

    When I run `wp widget add search sidebar-2 --title="Quick Search"`
    Then STDOUT should not be empty
    And I run `wp widget list sidebar-2 --format=count`
    Then STDOUT should be:
    """
    1
    """

    When I run `wp sidebar reset`
    And I run `wp widget list sidebar-1 --format=count`
    Then STDOUT should be:
    """
    0
    """

    When I run `wp widget list sidebar-2 --format=count`
    Then STDOUT should be:
    """
    0
    """
