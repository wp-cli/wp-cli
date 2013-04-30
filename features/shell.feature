Feature: WordPress REPL

  Scenario: Blank session
    Given a WP install

    When I run `wp shell < /dev/null`
    Then it should run without errors
    And STDOUT should be:
    """
    Type "exit" to close session.
    """

  Scenario: $_ special variable
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

  Scenario: Persistent environment
    Given a WP install
    And a session file:
    """
    function is_empty_string( $str ) { return strlen( $str ) == 0; }
    1; $a = get_option('home')
    is_empty_string( $a )
    """

    When I run `wp shell --quiet < session`
    Then it should run without errors
    And STDOUT should be:
    """
    1
    false
    """

  Scenario: History builtin
    Given a WP install
    And a session file:
    """
    defined('WP_CLI')
    function foo() {}
    history
    """

    When I run `wp shell --quiet < session`
    Then it should run without errors
    And STDOUT should be:
    """
    true
    defined('WP_CLI');
    function foo() {};
    """

  Scenario: Multiline support
    Given a WP install
    And a session file:
    """
    function is_empty_string( $str ) { \
        return strlen( $str ) == 0; \
    }

    function_exists( 'is_empty_string' )
    """

    When I run `wp shell --quiet < session`
    Then it should run without errors
    And STDOUT should be:
    """
    true
    """

