Feature: Skipping themes

  Scenario: Skipping themes via global flag
    Given a WP install
    And I run `wp theme install classic`
    And I run `wp theme install default --activate`

    When I run `wp eval 'var_export( function_exists( "kubrick_head" ) );'`
    Then STDOUT should be:
      """
      true
      """

    # The specified theme should be skipped
    When I run `wp --skip-themes=default eval 'var_export( function_exists( "kubrick_head" ) );'`
    Then STDOUT should be:
      """
      false
      """
    
    # All themes should be skipped
    When I run `wp --skip-themes eval 'var_export( function_exists( "kubrick_head" ) );'`
    Then STDOUT should be:
      """
      false
      """
    
    # Skip another theme
    When I run `wp --skip-themes=classic eval 'var_export( function_exists( "kubrick_head" ) );'`
    Then STDOUT should be:
      """
      true
      """
    
    # The specified theme should still show up as an active theme
    When I run `wp --skip-themes theme status default`
    Then STDOUT should contain:
      """
      Active
      """

    # Skip several themes
    When I run `wp --skip-themes=classic,default eval 'var_export( function_exists( "kubrick_head" ) );'`
    Then STDOUT should be:
      """
      false
      """

  Scenario: Skipping multiple themes via config file
    Given a WP install
    And a wp-cli.yml file:
      """
      skip-themes:
        - classic
        - default
      """
    And I run `wp theme install classic --activate`
    And I run `wp theme install default`
    
    # The classic theme should show up as an active theme
    When I run `wp theme status`
    Then STDOUT should contain:
      """
      A classic
      """

    # The default theme should show up as an installed theme
    When I run `wp theme status`
    Then STDOUT should contain:
      """
      I default
      """
    
    And I run `wp theme activate default`

    # The default theme should be skipped
    When I run `wp eval 'var_export( function_exists( "kubrick_head" ) );'`
    Then STDOUT should be:
      """
      false
      """
