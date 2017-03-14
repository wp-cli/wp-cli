Feature: Manage user term

  Scenario: Userterm CRUD
    Given a WP install
    And a wp-content/plugins/test-add-tax/plugin.php file:
      """
      <?php
      // Plugin Name: Test Add Tax

      function add_cli_tax(){
        register_taxonomy( 'user_type', 'user' );
      }

      add_action('init','add_cli_tax');
      """
    And I run `wp plugin activate test-add-tax`


    When I run `wp user term add 1 user_type foo`
    Then STDOUT should be:
      """
      Success: Added term.
      """

    When I run `wp user term list 1 user_type --fields=name,slug,taxonomy`
    Then STDOUT should be a table containing rows:
      | name | slug | taxonomy |
      | foo  | foo  | user_type |

    When I run `wp user term add 1 user_type bar`
    Then STDOUT should be:
      """
      Success: Added term.
      """

    When I run `wp user term list 1 user_type --fields=name,slug,taxonomy`
    Then STDOUT should be a table containing rows:
      | name | slug | taxonomy |
      | foo  | foo  | user_type |
      | bar  | bar  | user_type |

    When I run `wp user term list 1 user_type --format=ids`
    Then STDOUT should be:
      """
      3 2
      """

    When I run `wp user term set 1 user_type new`
    Then STDOUT should be:
      """
      Success: Set term.
      """

    When I run `wp user term list 1 user_type --fields=name,slug,taxonomy --format=count`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp user term remove 1 user_type new`
    Then STDOUT should be:
      """
      Success: Removed term.
      """

    When I run `wp user term list 1 user_type --fields=name,slug,taxonomy --format=count`
    Then STDOUT should be:
      """
      0
      """

  Scenario: Multiple user term
    Given a WP install

    And a wp-content/plugins/test-add-tax/plugin.php file:
      """
      <?php
      // Plugin Name: Test Add Tax

      function add_cli_tax(){
        register_taxonomy( 'user_type', 'user' );
      }

      add_action('init','add_cli_tax');
      """
    And I run `wp plugin activate test-add-tax`

    When I run `wp user term add 1 user_type apple`
    And I run `wp user term add 1 user_type apple`
    Then STDOUT should contain:
      """
      Success: Added term.
      """

    When I run `wp user term set 1 user_type apple1 apple2`
    Then STDOUT should contain:
      """
      Success: Set terms.
      """

    When I run `wp user term list 1 user_type --format=json --fields=name,slug,taxonomy`
    Then STDOUT should contain:
      """
      [{"name":"apple1","slug":"apple1","taxonomy":"user_type"},{"name":"apple2","slug":"apple2","taxonomy":"user_type"}]
      """

  Scenario: Userterm Add invalid tax
    Given a WP install

    When I try `wp user term add 1 boo foo2`
    Then the return code should be 1
    And STDERR should be:
      """
      Error: Invalid taxonomy boo.
      """
