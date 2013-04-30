Feature: WordPress REPL

  Scenario: Blank session
    Given a WP install

    When I run `wp shell < /dev/null`
    Then it should run without errors
    And STDOUT should be:
    """
    Type "exit" to close session.
    """
