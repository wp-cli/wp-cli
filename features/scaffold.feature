Feature: WordPress code scaffolding

  @theme
  Scenario: Scaffold a child theme
    Given a WP install
    Given I run `wp theme path`
    And save STDOUT as {THEME_DIR}

    When I run `wp scaffold child-theme zombieland --parent_theme=umbrella --theme_name=Zombieland --author=Tallahassee --author_uri=http://www.wp-cli.org --theme_uri=http://www.zombieland.com`
    Then STDOUT should not be empty
    And the {THEME_DIR}/zombieland/style.css file should exist

  Scenario: Scaffold a child theme and network enable it
    Given a WP multisite install

    When I run `wp scaffold child-theme zombieland --parent_theme=umbrella --theme_name=Zombieland --author=Tallahassee --author_uri=http://www.wp-cli.org --theme_uri=http://www.zombieland.com --enable-network`
    Then STDOUT should contain:
      """
      Success: Network enabled the 'Zombieland' theme.
      """

  @tax @cpt
  Scenario: Scaffold a Custom Taxonomy and Custom Post Type and write it to active theme
    Given a WP install
    Given I run `wp eval 'echo STYLESHEETPATH;'`
    And save STDOUT as {STYLESHEETPATH}

    When I run `wp scaffold taxonomy zombie-speed --theme`
    Then the {STYLESHEETPATH}/taxonomies/zombie-speed.php file should exist

    When I run `wp scaffold post-type zombie --theme`
    Then the {STYLESHEETPATH}/post-types/zombie.php file should exist

  # Test for all flags but --label, --theme, --plugin and --raw
  @tax
  Scenario: Scaffold a Custom Taxonomy and attach it to CPTs including one that is prefixed and has a text domain
    Given a WP install
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
    Given a WP install
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
    Given a WP install
    When I run `wp scaffold post-type zombie --textdomain=zombieland`
    Then STDOUT should contain:
      """
      __( 'Zombies'
      """
    And STDOUT should contain:
      """
      __( 'Zombies', 'zombieland'
      """
    And STDOUT should contain:
      """
      'menu_icon'         => 'dashicons-admin-post',
      """

  Scenario: CPT slug is too long
    Given a WP install
    When I try `wp scaffold post-type slugiswaytoolonginfact`
    Then STDERR should be:
      """
      Error: Post type slugs cannot exceed 20 characters in length.
      """

  @cpt
  Scenario: Scaffold a Custom Post Type with label
    Given a WP install
    When I run `wp scaffold post-type zombie --label="Brain eater"`
    Then STDOUT should contain:
      """
      __( 'Brain eaters'
      """

  Scenario: Scaffold a Custom Post Type with dashicon
    Given a WP install
    When I run `wp scaffold post-type zombie --dashicon="art"`
    Then STDOUT should contain:
      """
      'menu_icon'         => 'dashicons-art',
      """

  Scenario: Scaffold a plugin
    Given a WP install
    Given I run `wp plugin path`
    And save STDOUT as {PLUGIN_DIR}

    When I run `wp scaffold plugin hello-world`
    Then STDOUT should not be empty
    And the {PLUGIN_DIR}/hello-world/hello-world.php file should exist
    And the {PLUGIN_DIR}/hello-world/readme.txt file should exist
    And the {PLUGIN_DIR}/hello-world/package.json file should exist
    And the {PLUGIN_DIR}/hello-world/Gruntfile.js file should exist

  Scenario: Scaffold a plugin and activate it
    Given a WP install
    When I run `wp scaffold plugin hello-world --activate`
    Then STDOUT should contain:
      """
      Plugin 'hello-world' activated.
      """

  Scenario: Scaffold a plugin and network activate it
    Given a WP multisite install
    When I run `wp scaffold plugin hello-world --activate-network`
    Then STDOUT should contain:
      """
      Plugin 'hello-world' network activated.
      """

  Scenario: Scaffold plugin tests
    Given a WP install
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
    And the {PLUGIN_DIR}/hello-world/tests/bootstrap.php file should contain:
      """
      require dirname( dirname( __FILE__ ) ) . '/hello-world.php';
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
    Given a WP install
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
    Given a WP install
    Given I run `wp theme path`
    And save STDOUT as {THEME_DIR}

    When I run `wp scaffold _s starter-theme`
    Then STDOUT should contain:
      """
      Success: Created theme 'Starter-theme'.
      """
    And the {THEME_DIR}/starter-theme/style.css file should exist

  Scenario: Scaffold starter code for a theme with sass
    Given a WP install
    Given I run `wp theme path`
    And save STDOUT as {THEME_DIR}

    When I run `wp scaffold _s starter-theme --sassify`
    Then STDOUT should contain:
      """
      Success: Created theme 'Starter-theme'.
      """
    And the {THEME_DIR}/starter-theme/sass directory should exist

  Scenario: Scaffold starter code for a theme and activate it
    Given a WP install
    When I run `wp scaffold _s starter-theme --activate`
    Then STDOUT should contain:
      """
      Success: Switched to 'Starter-theme' theme.
      """

  Scenario: Scaffold plugin and tests for non-standard plugin directory
    Given a WP install

    When I run `wp scaffold plugin custom-plugin --dir=wp-content/mu-plugins --skip-tests`
    Then STDOUT should not be empty
    And the wp-content/mu-plugins/custom-plugin/custom-plugin.php file should exist
    And the wp-content/mu-plugins/custom-plugin/tests directory should not exist

    When I try `wp scaffold plugin-tests --dir=wp-content/mu-plugins/incorrect-custom-plugin`
    Then STDERR should contain:
      """
      Error: Invalid plugin directory specified.
      """

    When I run `wp scaffold plugin-tests --dir=wp-content/mu-plugins/custom-plugin`
    Then STDOUT should contain:
      """
      Success: Created test files.
      """
    And the wp-content/mu-plugins/custom-plugin/tests directory should exist
    And the wp-content/mu-plugins/custom-plugin/tests/bootstrap.php file should exist
    And the wp-content/mu-plugins/custom-plugin/tests/bootstrap.php file should contain:
    """
    require dirname( dirname( __FILE__ ) ) . '/custom-plugin.php';
    """

  Scenario: Scaffold tests for a plugin with a different slug than plugin directory
    Given a WP install
    And a wp-content/mu-plugins/custom-plugin2/custom-plugin-slug.php file:
      """
      <?php
      /**
       * Plugin Name: Handbook
       * Description: Features for a handbook, complete with glossary and table of contents
       * Author: Nacin
       */
      """

    When I run `wp scaffold plugin-tests custom-plugin-slug --dir=wp-content/mu-plugins/custom-plugin2`
    Then STDOUT should contain:
      """
      Success: Created test files.
      """
    And the wp-content/mu-plugins/custom-plugin2/tests directory should exist
    And the wp-content/mu-plugins/custom-plugin2/tests/bootstrap.php file should exist
    And the wp-content/mu-plugins/custom-plugin2/tests/bootstrap.php file should contain:
    """
    require dirname( dirname( __FILE__ ) ) . '/custom-plugin-slug.php';
    """

  Scenario: Scaffold starter code for a theme and network enable it
    Given a WP multisite install
    When I run `wp scaffold _s starter-theme --enable-network`
    Then STDOUT should contain:
      """
      Success: Network enabled the 'Starter-theme' theme.
      """

  Scenario: Scaffold starter code for a theme, but can't unzip theme files
    Given a WP install
    And a misconfigured WP_CONTENT_DIR constant directory
    When I try `wp scaffold _s starter-theme`
    Then STDERR should contain:
    """
    Error: Could not decompress your theme files
    """
