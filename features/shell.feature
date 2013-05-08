Feature: WordPress REPL

  Scenario: Blank session
    Given a WP install

    When I run `wp shell < /dev/null`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp shell --basic < /dev/null`
    Then it should run without errors
    And STDOUT should not be empty

  Scenario: Persistent environment
    Given a WP install
    And a session file:
    """
    function is_empty_string( $str ) { return strlen( $str ) == 0; }
    $a = get_option('home');
    is_empty_string( $a );
    """

    When I run `wp shell --basic --quiet < session`
    Then it should run without errors
    And STDOUT should contain:
    """
    bool(false)
    """

  Scenario: History builtin
    Given a WP install
    And a session file:
    """
    defined('WP_CLI')
    function foo() {}
    history
    """

    When I run `wp shell --basic --quiet < session`
    Then it should run without errors
    And STDOUT should be:
    """
    bool(true)
    defined('WP_CLI');
    function foo() {};
    """

  Scenario: Multiline support
    Given a WP install
    And a session file:
    """
    function is_empty_string( $str ) {
        return strlen( $str ) == 0;
    }

    function_exists( 'is_empty_string' );
    """

    When I run `wp shell --quiet < session`
    Then it should run without errors
    And STDOUT should be:
    """
     → NULL
     → bool(true)
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

    When I run `wp shell --basic --quiet < session`
    Then it should run without errors
    And STDOUT should be:
    """
    bool(true)
    """

