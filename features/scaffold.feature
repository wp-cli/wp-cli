Feature: Wordpress code scaffolding

  Background:
    Given a WP install

  Scenario: Scaffold a child theme
    Given I run `wp theme path`
    And save STDOUT as {THEME_DIR}

    When I run `wp scaffold child-theme zombieland --parent_theme=umbrella --theme_name=Zombieland --author=Tallahassee --author_uri=http://www.wp-cli.org --theme_uri=http://www.zombieland.com --activate`
    Then STDOUT should not be empty
    And the {THEME_DIR}/zombieland/style.css file should exist

  Scenario: Scaffold a Custom Taxonomy and Custom Post Type and write it to active theme
    Given I run `wp eval 'echo STYLESHEETPATH;'`
    And save STDOUT as {STYLESHEETPATH}

    When I run `wp scaffold taxonomy zombie-speed --theme`
    Then the {STYLESHEETPATH}/taxonomies/zombie-speed.php file should exist

    When I run `wp scaffold post-type zombie --theme`
    Then the {STYLESHEETPATH}/post-types/zombie.php file should exist

  # Test for all flags but --label, --theme, --plugin and --raw
  Scenario: Scaffold a Custom Taxonomy and attach it to CPTs including one that is prefixed and has a text domain
    When I run `wp scaffold taxonomy zombie-speed --post_types="prefix-zombie,wraith" --textdomain=zombieland`
    Then STDOUT should contain:
      """
      __( 'Zombie speeds'
      """
    And STDOUT should contain:
      """
      array( 'prefix-zombie', 'wraith' )
      """
    And STDOUT should contain:
      """
      __( 'Zombie speeds', 'zombieland'
      """
  
  Scenario: Scaffold a Custom Taxonomy with label "Speed"
    When I run `wp scaffold taxonomy zombie-speed --label="Speed"`
    Then STDOUT should contain:
        """
        __( 'Speeds'
        """
    And STDOUT should contain:
        """
        _x( 'Speed', 'taxonomy general name',
        """

  # Test for all flags but --label, --theme, --plugin and --raw
  Scenario: Scaffold a Custom Post Type
    When I run `wp scaffold post-type zombie --textdomain=zombieland`
    Then STDOUT should contain:
      """
      __( 'Zombies'
      """
    And STDOUT should contain:
      """
      __( 'Zombies', 'zombieland'
      """

  Scenario: Scaffold a Custom Post Type with label
    When I run `wp scaffold post-type zombie --label="Brain eater"`
    Then STDOUT should contain:
      """
      __( 'Brain eaters'
      """

  Scenario: Scaffold commands should ask for confirmation to overwrite if file already exists which can be overruled by passing --yes flag 
    When I run `wp scaffold cpt zombie-speed --theme`
    And I run `wp scaffold taxonomy zombie-speed --theme --yes`
    Then STDOUT should contain:
      """
      Created
      """
    When I run `wp scaffold post-type zombie --theme`
    When I run `wp scaffold post-type zombie --theme --yes`
    Then STDOUT should contain:
      """
      Created
      """

# Not working....
#    When I run `wp scaffold taxonomy zombie-speed --theme`
#    And I run `wp scaffold taxonomy zombie-speed --theme`
#    And STDERR should be:
#      """
#      Error: File already exists
#      """
#    Then the return code should be 1
 