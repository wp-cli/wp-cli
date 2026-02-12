Feature: Format output

  Scenario: Format output as YAML
    Given an empty directory
    And a output-yaml.php file:
      """
      <?php
      /**
       * Output data as YAML
       *
       * <type>
       * : Type of output.
       *
       * [--fields=<fields>]
       * : Limit output to particular fields
       *
       * @when before_wp_load
       */
      $output_yaml = function( $args, $assoc_args ) {
          $items = array(
              array(
                  'label'    => 'Foo',
                  'slug'     => 'foo',
              ),
              array(
                  'label'    => 'Bar',
                  'slug'     => 'bar',
              ),
          );
          $format_args = array( 'format' => 'yaml' );
          if ( isset( $assoc_args['fields'] ) ) {
              $format_args['fields'] = explode( ',', $assoc_args['fields'] );
          } else {
              $format_args['fields'] = array( 'label', 'slug' );
          }
          $formatter = new \WP_CLI\Formatter( $format_args );
          if ( 'all' === $args[0] ) {
          	  $formatter->display_items( $items );
          } else if ( 'single' === $args[0] ) {
              $formatter->display_item( $items[0] );
          }
      };
      WP_CLI::add_command( 'yaml', $output_yaml );
      """

    When I run `wp --require=output-yaml.php yaml all`
    Then STDOUT should be YAML containing:
      """
      ---
      -
        label: Foo
        slug: foo
      -
        label: Bar
        slug: bar
      """

    When I run `wp --require=output-yaml.php yaml all --fields=label`
    Then STDOUT should be YAML containing:
      """
      ---
      -
        label: Foo
      -
        label: Bar
      """
    And STDOUT should not contain:
      """
      slug: bar
      """

    When I run `wp --require=output-yaml.php yaml single`
    Then STDOUT should be YAML containing:
      """
      ---
      label: Foo
      slug: foo
      """

  Scenario: Format data in RTL language
    Given an empty directory
    And a file.php file:
      """
      <?php
      $items = array(
        array(
          'id' => 1,
          'language' => 'Afrikaans',
          'is_rtl' => 0,
        ),
        array(
          'id' => 2,
          'language' => 'العَرَبِيَّة‎‎',
          'is_rtl' => 1,
        ),
        array(
          'id' => 3,
          'language' => 'English',
          'is_rtl' => 0,
        ),
      );
      $assoc_args = array( 'format' => 'csv' );
      $formatter = new WP_CLI\Formatter( $assoc_args, array( 'id', 'language', 'is_rtl' ) );
      $formatter->display_items( $items );
      """

    When I run `wp eval-file file.php --skip-wordpress`
    Then STDOUT should be CSV containing:
      | id | language      | is_rtl |
      | 1  | Afrikaans     | 0      |
      | 2  | العَرَبِيَّة‎‎  | 1      |
      | 3  | English       | 0      |

  Scenario: Padding for pre-colorized tables
    Given an empty directory
    And a file.php file:
      """
      <?php
      use cli\Colors;
      /**
       * Fake command.
       *
       * ## OPTIONS
       *
       * [--format=<format>]
       * : Render output in a particular format.
       * ---
       * default: table
       * options:
       *   - table
       * ---
       *
       * @when before_wp_load
       */
      $fake_command = function( $args, $assoc_args ) {
          Colors::enable( true );
          $items = array(
              array( 'package' => Colors::colorize( '%ygaa/gaa-kabes%n' ), 'version' => 'dev-master', 'result' => Colors::colorize( "%r\xf0\x9f\x9b\x87%n" ) ),
              array( 'package' => Colors::colorize( '%ygaa/gaa-log%n' ), 'version' => '*', 'result' => Colors::colorize( "%g\xe2\x9c\x94%n" ) ),
              array( 'package' => Colors::colorize( '%ygaa/gaa-nonsense%n' ), 'version' => 'v3.0.11', 'result' => Colors::colorize( "%r\xf0\x9f\x9b\x87%n" ) ),
              array( 'package' => Colors::colorize( '%ygaa/gaa-100%%new%n' ), 'version' => 'v100%new', 'result' => Colors::colorize( "%g\xe2\x9c\x94%n" ) ),
          );
          $formatter = new \WP_CLI\Formatter( $assoc_args, array( 'package', 'version', 'result' ) );
          $formatter->display_items( $items, array( true, false, true ) );
      };
      WP_CLI::add_command( 'fake', $fake_command );
      """

    When I run `wp --require=file.php fake`
    Then STDOUT should be a table containing rows:
      | package          | version    | result |
      | [33mgaa/gaa-kabes[0m    | dev-master | [31m🛇[0m      |
      | [33mgaa/gaa-log[0m      | *          | [32m✔[0m      |
      | [33mgaa/gaa-nonsense[0m | v3.0.11    | [31m🛇[0m      |
      | [33mgaa/gaa-100%new[0m  | v100%new   | [32m✔[0m      |

  Scenario: Table rows containing linebreaks
    Given an empty directory
    And a file.php file:
      """
      <?php
      $items      = array(
        (object) array(
          'post_id'    => 1,
          'meta_key'   => 'foo',
          'meta_value' => 'foo',
        ),
        (object) array(
          'post_id'    => 1,
          'meta_key'   => 'fruits',
          'meta_value' => "apple\nbanana\nmango",
        ),
        (object) array(
          'post_id'    => 1,
          'meta_key'   => 'bar',
          'meta_value' => 'br',
        ),
      );
      $assoc_args = array();
      $formatter  = new WP_CLI\Formatter( $assoc_args, array( 'post_id', 'meta_key', 'meta_value' ) );
      $formatter->display_items( $items );
      """

    When I run `wp eval-file file.php --skip-wordpress`
    Then STDOUT should be a table containing rows:
      | post_id | meta_key | meta_value |
      | 1       | foo      | foo        |
      | 1       | fruits   | apple      |
      |         |          | banana     |
      |         |          | mango      |
      | 1       | bar      | br         |

  Scenario: Custom fields that exist in some items but not others
    Given an empty directory
    And a custom-fields.php file:
      """
      <?php
      $items = array(
        array(
          'name'   => 'Session 1',
          'custom' => 123,
          'login'  => '2018-09-15',
        ),
        array(
          'name'   => 'Session 2',
          'login'  => '2018-09-16',
        ),
        array(
          'name'   => 'Session 3',
          'custom' => 456,
          'login'  => '2018-09-17',
        ),
      );
      $assoc_args = array( 'format' => 'table', 'fields' => 'name,custom,login' );
      $formatter = new WP_CLI\Formatter( $assoc_args, array( 'name', 'custom', 'login' ) );
      $formatter->display_items( $items );
      """

    When I run `wp eval-file custom-fields.php --skip-wordpress`
    Then STDOUT should be a table containing rows:
      | name      | custom | login      |
      | Session 1 | 123    | 2018-09-15 |
      | Session 2 |        | 2018-09-16 |
      | Session 3 | 456    | 2018-09-17 |

  Scenario: Custom fields in CSV format with missing values
    Given an empty directory
    And a custom-fields-csv.php file:
      """
      <?php
      $items = array(
        array(
          'name'   => 'Session 1',
          'custom' => 123,
        ),
        array(
          'name'   => 'Session 2',
        ),
        array(
          'name'   => 'Session 3',
          'custom' => 456,
        ),
      );
      $assoc_args = array( 'format' => 'csv', 'fields' => 'name,custom' );
      $formatter = new WP_CLI\Formatter( $assoc_args, array( 'name', 'custom' ) );
      $formatter->display_items( $items );
      """

    When I run `wp eval-file custom-fields-csv.php --skip-wordpress`
    Then STDOUT should be CSV containing:
      | name      | custom |
      | Session 1 | 123    |
      | Session 2 |        |
      | Session 3 | 456    |

  Scenario: Custom fields in JSON format with missing values
    Given an empty directory
    And a custom-fields-json.php file:
      """
      <?php
      $items = array(
        array(
          'name'   => 'Session 1',
          'custom' => 123,
        ),
        array(
          'name'   => 'Session 2',
        ),
        array(
          'name'   => 'Session 3',
          'custom' => 456,
        ),
      );
      $assoc_args = array( 'format' => 'json', 'fields' => 'name,custom' );
      $formatter = new WP_CLI\Formatter( $assoc_args, array( 'name', 'custom' ) );
      $formatter->display_items( $items );
      """

    When I run `wp eval-file custom-fields-json.php --skip-wordpress`
    Then STDOUT should be JSON containing:
      """
      [{"name":"Session 1","custom":123},{"name":"Session 2","custom":null},{"name":"Session 3","custom":456}]
      """

  Scenario: Custom fields in YAML format with missing values
    Given an empty directory
    And a custom-fields-yaml.php file:
      """
      <?php
      $items = array(
        array(
          'name'   => 'Session 1',
          'custom' => 123,
        ),
        array(
          'name'   => 'Session 2',
        ),
        array(
          'name'   => 'Session 3',
          'custom' => 456,
        ),
      );
      $assoc_args = array( 'format' => 'yaml', 'fields' => 'name,custom' );
      $formatter = new WP_CLI\Formatter( $assoc_args, array( 'name', 'custom' ) );
      $formatter->display_items( $items );
      """

    When I run `wp eval-file custom-fields-yaml.php --skip-wordpress`
    Then STDOUT should be YAML containing:
      """
      ---
      -
        name: 'Session 1'
        custom: 123
      -
        name: 'Session 2'
        custom: ~
      -
        name: 'Session 3'
        custom: 456
      """

  Scenario: Warning when field doesn't exist in any items
    Given an empty directory
    And a no-field.php file:
      """
      <?php
      $items = array(
        array(
          'name'   => 'Session 1',
          'login'  => '2018-09-15',
        ),
        array(
          'name'   => 'Session 2',
          'login'  => '2018-09-16',
        ),
      );
      $assoc_args = array( 'format' => 'table', 'fields' => 'name,nonexistent,login' );
      $formatter = new WP_CLI\Formatter( $assoc_args, array( 'name', 'nonexistent', 'login' ) );
      $formatter->display_items( $items );
      """

    When I try `wp eval-file no-field.php --skip-wordpress`
    Then STDERR should contain:
      """
      Warning: Field not found in any item: nonexistent.
      """
    And STDOUT should be a table containing rows:
      | name      | nonexistent | login      |
      | Session 1 |             | 2018-09-15 |
      | Session 2 |             | 2018-09-16 |

  Scenario: No warning for missing field with empty list
    Given an empty directory
    And an empty-list-field.php file:
      """
      <?php
      $items = array();
      $assoc_args = array( 'format' => 'json', 'field' => 'name' );
      $formatter = new WP_CLI\Formatter( $assoc_args, array( 'name' ) );
      $formatter->display_items( $items );
      """

    When I run `wp eval-file empty-list-field.php --skip-wordpress`
    Then STDOUT should be:
      """
      []
      """
    And STDERR should be empty

  Scenario: No warning for missing fields with empty list
    Given an empty directory
    And an empty-list-fields.php file:
      """
      <?php
      $items = array();
      $assoc_args = array( 'format' => 'json', 'fields' => 'name,login' );
      $formatter = new WP_CLI\Formatter( $assoc_args, array( 'name', 'login' ) );
      $formatter->display_items( $items );
      """

    When I run `wp eval-file empty-list-fields.php --skip-wordpress`
    Then STDOUT should be:
      """
      []
      """
    And STDERR should be empty

  Scenario: Display ordered output for an object item
    Given an empty directory
    And a file.php file:
      """
      <?php
      $custom_obj = (object) [
        'name'    => 'Custom Name',
        'author'  => 'John Doe',
        'version' => '1.0'
      ];

      $assoc_args = [
        'format' => 'csv',
        'fields' => [ 'version', 'author', 'name' ],
      ];

      $formatter = new WP_CLI\Formatter( $assoc_args );
      $formatter->display_item( $custom_obj );
      """

    When I run `wp eval-file file.php --skip-wordpress`
    Then STDOUT should contain:
      """
      version,1.0
      author,"John Doe"
      name,"Custom Name"
      """

  Scenario: Display ordered output for an array item
    Given an empty directory
    And a file.php file:
      """
      <?php
      $custom_obj = [
        'name'    => 'Custom Name',
        'author'  => 'John Doe',
        'version' => '1.0'
      ];

      $assoc_args = [
        'format' => 'csv',
        'fields' => [ 'version', 'author', 'name' ],
      ];

      $formatter = new WP_CLI\Formatter( $assoc_args );
      $formatter->display_item( $custom_obj );
      """

    When I run `wp eval-file file.php --skip-wordpress`
    Then STDOUT should contain:
      """
      version,1.0
      author,"John Doe"
      name,"Custom Name"
      """

  Scenario: Table alignment with right and left aligned columns
    Given an empty directory
    And a file.php file:
      """
      <?php
      $items = array(
          array(
              'key'   => 'A',
              'value' => '100',
          ),
          array(
              'key'   => 'AB',
              'value' => '2000',
          ),
          array(
              'key'   => 'ABC',
              'value' => '30',
          ),
      );
      // 0 = right, 1 = left
      $assoc_args = array(
          'format' => 'table',
          'alignments' => array( 'key' => 0, 'value' => 1 ),
      );
      $formatter = new \WP_CLI\Formatter( $assoc_args, array( 'key', 'value' ) );
      $formatter->display_items( $items );
      """

    When I run `SHELL_PIPE=0 wp eval-file file.php --skip-wordpress`
    Then STDOUT should strictly be:
      """
      +-----+-------+
      | key | value |
      +-----+-------+
      |   A | 100   |
      |  AB | 2000  |
      | ABC | 30    |
      +-----+-------+
      """

  Scenario: Table alignment with center aligned columns
    Given an empty directory
    And a file.php file:
      """
      <?php
      $items = array(
          array(
              'key'   => 'A',
              'value' => '1',
          ),
          array(
              'key'   => 'ABC',
              'value' => '123',
          ),
      );
      // 2 = center
      $assoc_args = array(
          'format' => 'table',
          'alignments' => array( 'key' => 2, 'value' => 2 ),
      );
      $formatter = new \WP_CLI\Formatter( $assoc_args, array( 'key', 'value' ) );
      $formatter->display_items( $items );
      """

    When I run `SHELL_PIPE=0 wp eval-file file.php --skip-wordpress`
    Then STDOUT should strictly be:
      """
      +-----+-------+
      | key | value |
      +-----+-------+
      |  A  |   1   |
      | ABC |  123  |
      +-----+-------+
      """

  Scenario: Table truncates overly large values
    Given an empty directory
    And a file.php file:
      """
      <?php
      $large_value = str_repeat( 'x', 3000 ); // Create a 3000 character string
      $items = array(
        (object) array(
          'id'    => 1,
          'value' => 'short',
        ),
        (object) array(
          'id'    => 2,
          'value' => $large_value,
        ),
        (object) array(
          'id'    => 3,
          'value' => 'another short',
        ),
      );
      $assoc_args = array();
      $formatter  = new WP_CLI\Formatter( $assoc_args, array( 'id', 'value' ) );
      $formatter->display_items( $items );
      """

    When I run `wp eval-file file.php --skip-wordpress`
    Then STDOUT should contain:
      """
      short
      """
    And STDOUT should contain:
      """
      xxx...
      """
    And STDOUT should contain:
      """
      another short
      """
    And STDOUT should not contain:
      """
      xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
      """

  Scenario: Format output using prefix without warnings
    Given an empty directory
    And a file.php file:
      """
      <?php
      $items = array(
          array(
              'post_type' => 'page',
              'post_name' => 'sample-page',
          ),
      );
      $assoc_args = array(
          'format' => 'table',
      );
      // 'post' prefix should map 'type' to 'post_type' and 'name' to 'post_name'
      $formatter = new \WP_CLI\Formatter( $assoc_args, array( 'type', 'name' ), 'post' );
      $formatter->display_item( $items[0] );
      """

    When I run `wp eval-file file.php --skip-wordpress`
    Then STDOUT should be a table containing rows:
      | Field     | Value       |
      | post_type | page        |
      | post_name | sample-page |
    And STDERR should be empty

  Scenario: Register and use custom format
    Given an empty directory
    And a custom-format.php file:
      """
      <?php
      // Register a custom XML format
      WP_CLI\Formatter::add_format( 'xml', function( $items, $fields ) {
          echo "<?xml version=\"1.0\"?>\n<items>\n";
          foreach ( $items as $item ) {
              echo "  <item>\n";
              foreach ( $item as $key => $value ) {
                  echo "    <{$key}>" . htmlspecialchars( $value ) . "</{$key}>\n";
              }
              echo "  </item>\n";
          }
          echo "</items>\n";
      });

      /**
       * Test command with custom format
       *
       * [--format=<format>]
       * : Output format
       * ---
       * default: table
       * options:
       *   - table
       *   - json
       *   - xml
       * ---
       *
       * @when before_wp_load
       */
      $test_command = function( $args, $assoc_args ) {
          $items = array(
              array( 'name' => 'Alice', 'age' => '30' ),
              array( 'name' => 'Bob', 'age' => '25' ),
          );
          $formatter = new \WP_CLI\Formatter( $assoc_args, array( 'name', 'age' ) );
          $formatter->display_items( $items );
      };
      WP_CLI::add_command( 'test-format', $test_command );
      """

    When I run `wp --require=custom-format.php test-format --format=xml`
    Then STDOUT should contain:
      """
      <?xml version="1.0"?>
      <items>
        <item>
          <name>Alice</name>
          <age>30</age>
        </item>
        <item>
          <name>Bob</name>
          <age>25</age>
        </item>
      </items>
      """
    And the return code should be 0

  Scenario: Filter available formats
    Given an empty directory
    And a filter-formats.php file:
      """
      <?php
      // Add a custom format
      WP_CLI\Formatter::add_format( 'custom1', function( $items, $fields ) {
          echo "CUSTOM1\n";
      });

      // Filter to add another format to the list
      WP_CLI::add_hook( 'formatter_available_formats', function( $formats ) {
          $formats[] = 'custom2';
          return $formats;
      });

      // Get available formats
      $formats = WP_CLI\Formatter::get_available_formats();
      WP_CLI::line( 'Available formats: ' . implode( ', ', $formats ) );
      """

    When I run `wp --require=filter-formats.php eval 'exit(0);'`
    Then STDOUT should contain:
      """
      Available formats: table, json, csv, yaml, count, ids, custom1, custom2
      """
    And the return code should be 0

  Scenario: Custom format error handling
    Given an empty directory
    And a invalid-format.php file:
      """
      <?php
      /**
       * Test command
       *
       * @when before_wp_load
       */
      $test_command = function( $args, $assoc_args ) {
          $items = array(
              array( 'name' => 'Test' ),
          );
          $formatter = new \WP_CLI\Formatter( $assoc_args, array( 'name' ) );
          $formatter->display_items( $items );
      };
      WP_CLI::add_command( 'test-invalid', $test_command );
      """

    When I try `wp --require=invalid-format.php test-invalid --format=nonexistent`
    Then STDERR should contain:
      """
      Invalid format: nonexistent
      """
    And the return code should be 1
