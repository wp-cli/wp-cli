Feature: WP-CLI Commands

  Scenario: Invalid class is specified for a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php

      WP_CLI::add_command( 'command example', 'Non_Existent_Class' );
      """

    When I try `wp --require=custom-cmd.php help`
    Then the return code should be 1
    And STDERR should contain:
      """
      Class 'Non_Existent_Class' does not exist.
      """

  Scenario: Invalid subcommand of valid command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php
      /**
       * @when before_wp_load
       */
      class Custom_Command_Class extends WP_CLI_Command {

          public function valid() {
             WP_CLI::success( 'Hello world' );
          }

      }
      WP_CLI::add_command( 'command', 'Custom_Command_Class' );
      """

    When I try `wp --require=custom-cmd.php command invalid`
    Then STDERR should be:
      """
      Error: 'invalid' is not a registered subcommand of 'command'. See 'wp help command'.
      """
