Feature: WordPress REPL

  Scenario: Blank session
    Given a WP install

    When I run `wp shell < /dev/null`

    When I run `wp shell --basic < /dev/null`
    Then STDOUT should be empty

  Scenario: Persistent environment
    Given a WP install
    And a session file:
      """
      function is_empty_string( $str ) { return strlen( $str ) == 0; }
      $a = get_option('home');
      is_empty_string( $a );
      """

    When I run `wp shell --basic < session`
    Then STDOUT should contain:
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
    Then STDOUT should contain:
      """
      bool(true)
      """

