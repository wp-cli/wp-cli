Feature: Wordpress code scaffolding

  Scenario: Scaffold a Custom Taxonomy and write it to active theme
    Given a WP install

    When I run `wp scaffold taxonomy zombie-speed --theme`
    Then it should run without errors

    When I run `wp eval 'echo STYLESHEETPATH;'`
    Then it should run without errors
    And save STDOUT as {STYLESHEETPATH}
    And the {STYLESHEETPATH}/taxonomies/zombie-speed.php file should exist

  # Test for all flags but --label, --theme, --plugin and --raw
  Scenario: Scaffold a Custom Taxonomy and attach it to a CPT zombie that is prefixed and has a text domain
    Given a WP install

    When I run `wp scaffold taxonomy zombie-speed --post_types="prefix-zombie" --textdomain=zombieland`
    Then it should run without errors
    And STDOUT should contain:
      """
      __( 'Zombie speeds'
      """
    And STDOUT should contain:
      """
      array( 'prefix-zombie' )
      """
    And STDOUT should contain:
      """
      __( 'Zombie speeds', 'zombieland'
      """

  Scenario: Scaffold a Custom Taxonomy with label "Speed"
    Given a WP install

    When I run `wp scaffold taxonomy zombie-speed --label="Speed"`
    Then it should run without errors
    And STDOUT should contain:
        """
        __( 'Speed'
        """



  # Test for all flags but --label, --theme, --plugin and --raw
  Scenario: Scaffold a Custom Post Type
    Given a WP install

    When I run `wp scaffold post-type zombie --textdomain=zombieland`
    Then it should run without errors
    And STDOUT should contain:
      """
      __( 'Zombies'
      """
    And STDOUT should contain:
      """
      __( 'Zombies', 'zombieland'
      """

  Scenario: Scaffold a Custom Post Type with label
    Given a WP install

    When I run `wp scaffold post-type zombie --label="Brain eater"`
    Then it should run without errors
    And STDOUT should contain:
      """
      __( 'Brain eaters'
      """