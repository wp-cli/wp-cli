Feature: WP-CLI Commands

  Scenario: Invalid class is specified for a command
    Given an empty directory
    And a custom-cmd.php file:
      """
      <?php

      WP_CLI::add_command( 'command example', 'Non_Existent_Class' );
      """

    When I run `wp --require=custom-cmd.php help`
    Then STDOUT should contain:
      """
      Error: Class 'Non_Existent_Class' does not exist.
      """
