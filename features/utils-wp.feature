Feature: Utilities that depend on WordPress code

  Scenario: Clear WP cache
    Given a WP install
    And a test.php file:
      """
      <?php
      WP_CLI::add_hook( 'after_wp_load', function () {
        global $wp_object_cache;
        echo empty( $wp_object_cache->cache ) . ',' . isset( $wp_object_cache->group_ops ) . ',' . isset( $wp_object_cache->stats ) . ',' . isset( $wp_object_cache->memcache_debug ) . "\n";
        WP_CLI\Utils\wp_clear_object_cache();
        echo empty( $wp_object_cache->cache ) . ',' . isset( $wp_object_cache->group_ops ) . ',' . isset( $wp_object_cache->stats ) . ',' . isset( $wp_object_cache->memcache_debug ) . "\n";
      } );
      """

    When I run `wp post create --post_title="Foo Bar" --porcelain`
    And I run `wp --require=test.php eval ''`
    Then STDOUT should be:
      """
      ,,,
      1,,,
      """
