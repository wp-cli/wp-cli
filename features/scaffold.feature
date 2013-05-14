Feature: Wordpress code scaffolding

  @theme
  Scenario: Scaffold a child theme
    Given a WP install

    When I run `wp scaffold child-theme zombieland --parent_theme=umbrella --theme_name=Zombieland --author=Tallahassee --author_uri=http://www.wp-cli.org --theme_uri=http://www.zombieland.com  --activate`
    Then it should run without errors
    
    When I run `wp theme path`
    Then it should run without errors
    And save STDOUT as {THEME_PATH}
    And the {THEME_PATH}/zombieland/style.css file should exist

  # Adding --activate to the test crashes the tests
  @plugin
  Scenario: Scaffold a plugin
    Given a WP install

    When I run `wp scaffold plugin zombieland --plugin_name="Welcome to Zombieland"`
    Then it should run without errors
    
    When I run `wp plugin path`
    Then it should run without errors
    And save STDOUT as {PLUGIN_PATH}
    And the {PLUGIN_PATH}/zombieland/zombieland.php file should exist

    When I run `wp plugin activate zombieland`
    Then it should run without errors
    
  @tax @cpt
  Scenario: Scaffold a Custom Taxonomy and Custom Post Type and write it to active theme
    Given a WP install

    When I run `wp eval 'echo STYLESHEETPATH;'`
    Then it should run without errors
    And save STDOUT as {STYLESHEETPATH}

    When I run `wp scaffold taxonomy zombie-speed --theme`
    Then it should run without errors
    And the {STYLESHEETPATH}/taxonomies/zombie-speed.php file should exist

    When I run `wp scaffold post-type zombie --theme`
    Then it should run without errors
    And the {STYLESHEETPATH}/post-types/zombie.php file should exist

  # Test for all flags but --label, --theme, --plugin and --raw
  @tax
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

  @tax
  Scenario: Scaffold a Custom Taxonomy with label "Speed"
    Given a WP install

    When I run `wp scaffold taxonomy zombie-speed --label="Speed"`
    Then it should run without errors
    And STDOUT should contain:
        """
        __( 'Speed'
        """

  # Test for all flags but --label, --theme, --plugin and --raw
  @cpt
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

  @cpt
  Scenario: Scaffold a Custom Post Type with label
    Given a WP install

    When I run `wp scaffold post-type zombie --label="Brain eater"`
    Then it should run without errors
    And STDOUT should contain:
      """
      __( 'Brain eaters'
      """
