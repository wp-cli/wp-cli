Feature: Managed the WordPress object cache

  Scenario: Default group is 'default'
    Given a WP install
    And a wp-content/mu-plugins/test-harness.php file:
      """
      <?php
      $set_foo = function(){
        wp_cache_set( 'foo', 'bar' );
      };
      $set_foo_value = function() {
        wp_cache_set( 'foo', 2 );
      };
      $log_foo_value = function() {
        WP_CLI::log( var_export( wp_cache_get( 'foo' ), true ) );
      };
      WP_CLI::add_hook( 'before_invoke:cache get', $set_foo );
      WP_CLI::add_hook( 'before_invoke:cache delete', $set_foo );
      WP_CLI::add_hook( 'before_invoke:cache add', $set_foo );
      WP_CLI::add_hook( 'before_invoke:cache incr', $set_foo_value );
      WP_CLI::add_hook( 'before_invoke:cache decr', $set_foo_value );
      WP_CLI::add_hook( 'after_invoke:cache set', $log_foo_value );
      WP_CLI::add_hook( 'before_invoke:cache replace', $set_foo_value );
      """

    When I run `wp cache get foo`
    Then STDOUT should be:
      """
      bar
      """

    When I try `wp cache get bar`
    Then STDERR should be:
      """
      Error: Object with key 'bar' and group 'default' not found.
      """

    When I try `wp cache get bar burrito`
    Then STDERR should be:
      """
      Error: Object with key 'bar' and group 'burrito' not found.
      """

    When I run `wp cache delete foo`
    Then STDOUT should be:
      """
      Success: Object deleted.
      """

    When I try `wp cache delete bar`
    Then STDERR should be:
      """
      Error: The object was not deleted.
      """

    When I try `wp cache add foo bar`
    Then STDERR should be:
      """
      Error: Could not add object 'foo' in group 'default'. Does it already exist?
      """

    When I run `wp cache add bar burrito`
    Then STDOUT should be:
      """
      Success: Added object 'bar' in group 'default'.
      """

    When I run `wp cache add bar foo burrito`
    Then STDOUT should be:
      """
      Success: Added object 'bar' in group 'burrito'.
      """

    When I run `wp cache incr foo`
    Then STDOUT should be:
      """
      3
      """

    When I run `wp cache incr foo 2`
    Then STDOUT should be:
      """
      4
      """

    When I try `wp cache incr bar`
    Then STDERR should be:
      """
      Error: The value was not incremented.
      """

    When I run `wp cache decr foo`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp cache decr foo 2`
    Then STDOUT should be:
      """
      0
      """

    When I try `wp cache decr bar`
    Then STDERR should be:
      """
      Error: The value was not decremented.
      """

    When I run `wp cache set foo bar`
    Then STDOUT should be:
      """
      Success: Set object 'foo' in group 'default'.
      'bar'
      """

    When I run `wp cache set burrito foo bar`
    Then STDOUT should be:
      """
      Success: Set object 'burrito' in group 'bar'.
      false
      """

    When I run `wp cache replace foo burrito`
    Then STDOUT should be:
      """
      Success: Replaced object 'foo' in group 'default'.
      """

    When I try `wp cache replace bar burrito foo`
    Then STDERR should be:
      """
      Error: Could not replace object 'bar' in group 'foo'. Does it not exist?
      """
