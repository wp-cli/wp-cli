Feature: WordPress REPL

  Scenario: Blank session
    Given a WP install

    When I run `wp shell < /dev/null`
    Then it should run without errors
    And STDOUT should be:
    """
    Type "exit" to close session.
    """

  Scenario: Basic session
    Given a WP install
    And a session file:
    """
    WP_ADMIN
    $_
    get_current_user_id()
    $_
    """

    When I run `wp shell --quiet < session`
    Then it should run without errors
    And STDOUT should be:
    """
    true
    true
    0
    0
    """
