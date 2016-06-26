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
