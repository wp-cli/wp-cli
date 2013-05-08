Feature: WordPress REPL

  Scenario: Blank session
    Given a WP install

    When I run `wp shell < /dev/null`
    Then it should run without errors

    When I run `wp shell --basic < /dev/null`
    Then it should run without errors

  Scenario: Persistent environment
    Given a WP install
    And a session file:
    """
    function is_empty_string( $str ) { return strlen( $str ) == 0; }
    $a = get_option('home');
    is_empty_string( $a );
    """

    When I run `wp shell --basic < session`
    Then it should run without errors
    And STDOUT should contain:
    """
    bool(false)
    """

  Scenario: Multiline support (basic)
    Given a WP install
    And a session file:
    """
    function is_empty_string( $str ) { \
        return strlen( $str ) == 0; \
    }

    function_exists( 'is_empty_string' );
    """

    When I run `wp shell --basic < session`
    Then it should run without errors
    And STDOUT should be:
    """
    bool(true)
    """

