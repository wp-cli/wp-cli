Feature: WordPress code scaffolding

  Background:
    Given a WP install

  @theme
  Scenario: Scaffold a child theme
    Given I run `wp theme path`
    And save STDOUT as {THEME_DIR}

    When I run `wp scaffold child-theme zombieland --parent_theme=umbrella --theme_name=Zombieland --author=Tallahassee --author_uri=http://www.wp-cli.org --theme_uri=http://www.zombieland.com --activate`
    Then STDOUT should not be empty
    And the {THEME_DIR}/zombieland/style.css file should exist

  @tax @cpt
  Scenario: Scaffold a Custom Taxonomy and Custom Post Type and write it to active theme
    Given I run `wp eval 'echo STYLESHEETPATH;'`
    And save STDOUT as {STYLESHEETPATH}

    When I run `wp scaffold taxonomy zombie-speed --theme`
    Then the {STYLESHEETPATH}/taxonomies/zombie-speed.php file should exist

    When I run `wp scaffold post-type zombie --theme`
    Then the {STYLESHEETPATH}/post-types/zombie.php file should exist

  # Test for all flags but --label, --theme, --plugin and --raw
  @tax
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
  
  @tax
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
  @cpt
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

  Scenario: CPT slug is too long
    When I try `wp scaffold post-type slugiswaytoolonginfact`
    Then STDERR should be:
      """
      Error: Post type slugs cannot exceed 20 characters in length.
      """

  @cpt
  Scenario: Scaffold a Custom Post Type with label
    When I run `wp scaffold post-type zombie --label="Brain eater"`
    Then STDOUT should contain:
      """
      __( 'Brain eaters'
      """

  Scenario: Scaffold a plugin
    Given I run `wp plugin path`
    And save STDOUT as {PLUGIN_DIR}

    When I run `wp scaffold plugin hello-world`
    Then STDOUT should not be empty
    And the {PLUGIN_DIR}/hello-world/hello-world.php file should exist
    And the {PLUGIN_DIR}/hello-world/readme.txt file should exist

  Scenario: Scaffold plugin tests
    When I run `wp plugin path`
    Then save STDOUT as {PLUGIN_DIR}

    When I run `wp scaffold plugin hello-world --skip-tests`
    Then STDOUT should not be empty
    And the {PLUGIN_DIR}/hello-world/hello-world.php file should exist
    And the {PLUGIN_DIR}/hello-world/readme.txt file should exist
    And the {PLUGIN_DIR}/hello-world/tests directory should not exist

    When I run `wp scaffold plugin-tests hello-world`
    Then STDOUT should not be empty
    And the {PLUGIN_DIR}/hello-world/tests directory should contain:
      """
      bootstrap.php
      test-sample.php
      """
    And the {PLUGIN_DIR}/hello-world/bin directory should contain:
      """
      install-wp-tests.sh
      """
    And the {PLUGIN_DIR}/hello-world/phpunit.xml file should exist
    And the {PLUGIN_DIR}/hello-world/.travis.yml file should exist

    When I run `wp eval "if ( is_executable( '{PLUGIN_DIR}/hello-world/bin/install-wp-tests.sh' ) ) { echo 'executable'; } else { exit( 1 ); }"`
    Then STDOUT should be:
      """
      executable
      """

  Scenario: Scaffold package tests
    Given a community-command/command.php file:
      """
      <?php
      """
    And a community-command/composer.json file:
      """
      {
        "name": "wp-cli/community-command",
        "description": "A demo community command.",
        "license": "MIT",
        "minimum-stability": "dev",
        "require": {
        },
        "autoload": {
          "files": [ "dictator.php" ]
        },
        "require-dev": {
          "behat/behat": "~2.5"
        }
      }
      """
    And a invalid-command/command.php file:
      """
      <?php
      """

    When I run `wp scaffold package-tests community-command`
    Then STDOUT should not be empty
    And the community-command/.travis.yml file should exist
    And the community-command/bin/install-package-tests.sh file should exist
    And the community-command/utils/get-package-require-from-composer.php file should exist
    And the community-command/features directory should contain:
      """
      bootstrap
      extra
      load-wp-cli.feature
      steps
      """
    And the community-command/features/bootstrap directory should contain:
      """
      FeatureContext.php
      Process.php
      support.php
      utils.php
      """
    And the community-command/features/steps directory should contain:
      """
      given.php
      then.php
      when.php
      """
    And the community-command/features/extra directory should contain:
      """
      no-mail.php
      """

    When I run `wp eval "if ( is_executable( 'community-command/bin/install-package-tests.sh' ) ) { echo 'executable'; } else { exit( 1 ); }"`
    Then STDOUT should be:
      """
      executable
      """

    When I try `wp scaffold package-tests invalid-command`
    Then STDERR should be:
      """
      Error: Invalid package directory. composer.json file must be present.
      """

  Scenario: Scaffold starter code for a theme
    Given I run `wp theme path`
    And save STDOUT as {THEME_DIR}

    When I run `wp scaffold _s starter-theme`
    Then STDOUT should contain:
      """
      Success: Created theme 'Starter-theme'.
      """
    And the {THEME_DIR}/starter-theme/style.css file should exist

