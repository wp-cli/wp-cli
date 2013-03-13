Feature: Global flags

  Scenario: Quiet run
    Given a WP install

    When I run `wp`
    Then it should run without errors
    And STDOUT should not be empty

    When I run `wp --quiet`
    Then it should run without errors
    And STDOUT should be empty
 
    When I run `wp non-existing-command --quiet`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: 'non-existing-command' is not a registered wp command. See 'wp help'.
      """

  Scenario: Debug run
    Given a WP install

    When I run `wp eval 'echo CONST_WITHOUT_QUOTES;'`
    Then it should run without errors
    And STDOUT should be:
      """
      CONST_WITHOUT_QUOTES
      """

    When I run `wp --debug eval 'echo CONST_WITHOUT_QUOTES;'`
    Then the return code should be 0
    And STDOUT should contain:
      """
      Notice: Use of undefined constant CONST_WITHOUT_QUOTES
      """

  Scenario: Setting the WP user
    Given a WP install

    When I run `wp eval 'echo (int) is_user_logged_in();'`
    Then it should run without errors
    And STDOUT should be:
      """
      0
      """

    When I run `wp --user=admin eval 'echo wp_get_current_user()->user_login;'`
    Then it should run without errors
    And STDOUT should be:
      """
      admin
      """

    When I run `wp --user=non-existing-user`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: Could not get a user_id for this user: 'non-existing-user'
      """

  Scenario: Enabling/disabling color
    Given a WP install

    When I run `wp --no-color non-existent-command`
    Then STDERR should be:
      """
      Error: 'non-existent-command' is not a registered wp command. See 'wp help'.
      """

    When I run `wp --color non-existent-command`
    Then STDERR should contain:
      """
      [31;1mError:
      """
