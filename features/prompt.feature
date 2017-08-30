Feature: Prompt user for input

  Scenario: Flag prompt should be case insensitive
    Given an empty directory
    And a cmd.php file:
      """
      <?php
      /**
       * Test that the flag prompt is case insensitive.
       *
       * ## OPTIONS
       *
       * [--flag]
       * : An optional flag
       *
       * @when before_wp_load
       */
      WP_CLI::add_command( 'test-prompt', function( $_, $assoc_args ){
        var_dump( WP_CLI\Utils\get_flag_value( $assoc_args, 'flag' ) );
      });
      """
    And a uppercase-session file:
      """
      Y
      """
    And a lowercase-session file:
      """
      y
      """
    And a wp-cli.yml file:
      """
      require:
        - cmd.php
      """

    When I run `wp test-prompt --prompt < uppercase-session`
    Then STDOUT should be:
      """
      1/1 [--flag] (Y/n): Y
      bool(true)
      """

    When I run `wp test-prompt --prompt < lowercase-session`
    Then STDOUT should be:
      """
      1/1 [--flag] (Y/n): y
      bool(true)
      """
