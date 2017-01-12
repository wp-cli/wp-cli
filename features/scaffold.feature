Feature: WordPress code scaffolding

  @theme
  Scenario: Scaffold a child theme
    Given a WP install
    Given I run `wp theme path`
    And save STDOUT as {THEME_DIR}

    When I run `wp scaffold child-theme zombieland --parent_theme=umbrella --theme_name=Zombieland --author=Tallahassee --author_uri=http://www.wp-cli.org --theme_uri=http://www.zombieland.com`
    Then the {THEME_DIR}/zombieland/style.css file should exist
    And the {THEME_DIR}/zombieland/functions.php file should exist
    And STDOUT should be:
      """
      Success: Created '{THEME_DIR}/zombieland'.
      """

  Scenario: Scaffold a child theme with only --parent_theme parameter
    Given a WP install
    Given I run `wp theme path`
    And save STDOUT as {THEME_DIR}

    When I run `wp scaffold child-theme hello-world --parent_theme=simple-life`
    Then STDOUT should not be empty
    And the {THEME_DIR}/hello-world/style.css file should exist
    And the {THEME_DIR}/hello-world/style.css file should contain:
      """
      Theme Name:     Hello-world
      """

  Scenario: Scaffold a child theme with non existing parent theme and also activate parameter
    Given a WP install

    When I try `wp scaffold child-theme hello-world --parent_theme=just-test --activate --quiet`
    Then STDERR should contain:
      """
      Error: The parent theme is missing. Please install the "just-test" parent theme.
      """

  Scenario: Scaffold a child theme with non existing parent theme and also network activate parameter
    Given a WP install

    When I try `wp scaffold child-theme hello-world --parent_theme=just-test --enable-network --quiet`
    Then STDERR should contain:
      """
      Error: This is not a multisite install.
      """

  Scenario: Scaffold a child theme and network enable it
    Given a WP multisite install

    When I run `wp scaffold child-theme zombieland --parent_theme=umbrella --theme_name=Zombieland --author=Tallahassee --author_uri=http://www.wp-cli.org --theme_uri=http://www.zombieland.com --enable-network`
    Then STDOUT should contain:
      """
      Success: Network enabled the 'Zombieland' theme.
      """

  Scenario: Scaffold a child theme with invalid slug
    Given a WP install
    When I try `wp scaffold child-theme . --parent_theme=simple-life`
    Then STDERR should contain:
      """
      Error: Invalid theme slug specified.
      """
    When I try `wp scaffold child-theme ../ --parent_theme=simple-life`
    Then STDERR should contain:
      """
      Error: Invalid theme slug specified.
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
    And STDOUT should be:
      """
      Success: Created '{STYLESHEETPATH}/post-types/zombie.php'.
      """

    When I run `wp scaffold post-type zombie`
    Then STDOUT should contain:
      """
      register_post_type( 'zombie'
      """
    And STDOUT should contain:
      """
      add_filter( 'post_updated_messages'
      """
    When I run `wp scaffold post-type zombie --raw`
    Then STDOUT should not contain:
      """
      add_filter( 'post_updated_messages'
      """

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
    Given I run `wp core version`
    And save STDOUT as {WP_VERSION}

    When I run `wp scaffold plugin hello-world --plugin_author="Hello World Author"`
    Then STDOUT should not be empty
    And the {PLUGIN_DIR}/hello-world/.gitignore file should exist
    And the {PLUGIN_DIR}/hello-world/.editorconfig file should exist
    And the {PLUGIN_DIR}/hello-world/hello-world.php file should exist
    And the {PLUGIN_DIR}/hello-world/readme.txt file should exist
    And the {PLUGIN_DIR}/hello-world/package.json file should exist
    And the {PLUGIN_DIR}/hello-world/Gruntfile.js file should exist
    And the {PLUGIN_DIR}/hello-world/.gitignore file should contain:
      """
      .DS_Store
      Thumbs.db
      wp-cli.local.yml
      node_modules/
      """
    And the {PLUGIN_DIR}/hello-world/.distignore file should contain:
      """
      .git
      .gitignore
      """
    And the {PLUGIN_DIR}/hello-world/hello-world.php file should contain:
      """
      * Plugin Name:     Hello World
      """
    And the {PLUGIN_DIR}/hello-world/hello-world.php file should contain:
      """
      * Version:         0.1.0
      """
    And the {PLUGIN_DIR}/hello-world/hello-world.php file should contain:
      """
      * @package         Hello_World
      """
    And the {PLUGIN_DIR}/hello-world/readme.txt file should contain:
      """
      Stable tag: 0.1.0
      """
    And the {PLUGIN_DIR}/hello-world/readme.txt file should contain:
      """
      Tested up to: {WP_VERSION}
      """

    When I run `cat {PLUGIN_DIR}/hello-world/package.json`
    Then STDOUT should be JSON containing:
      """
      {"author":"Hello World Author"}
      """
    And STDOUT should be JSON containing:
      """
      {"version":"0.1.0"}
      """

  Scenario: Scaffold a plugin by prompting
    Given a WP install
    And a session file:
      """
      hello-world

      Hello World
      An awesome introductory plugin for WordPress
      WP-CLI
      http://wp-cli.org
      http://wp-cli.org
      n
      travis
      Y
      n
      n
      """

    When I run `wp scaffold plugin --prompt < session`
    Then STDOUT should not be empty
    And the wp-content/plugins/hello-world/hello-world.php file should exist
    And the wp-content/plugins/hello-world/readme.txt file should exist
    And the wp-content/plugins/hello-world/tests directory should exist

    When I run `wp plugin status hello-world`
    Then STDOUT should contain:
      """
      Status: Active
      """
    And STDOUT should contain:
      """
      Name: Hello World
      """
    And STDOUT should contain:
      """
      Description: An awesome introductory plugin for WordPress
      """

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

  Scenario: Scaffold a plugin with invalid slug
    Given a WP install
    When I try `wp scaffold plugin .`
    Then STDERR should contain:
      """
      Error: Invalid plugin slug specified.
      """
    When I try `wp scaffold plugin ../`
    Then STDERR should contain:
      """
      Error: Invalid plugin slug specified.
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

  Scenario: Scaffold starter code for a theme with invalid slug
    Given a WP install
    When I try `wp scaffold _s .`
    Then STDERR should contain:
      """
      Error: Invalid theme slug specified.
      """
    When I try `wp scaffold _s ../`
    Then STDERR should contain:
      """
      Error: Invalid theme slug specified.
      """
    When I try `wp scaffold _s 1themestartingwithnumber`
    Then STDERR should contain:
      """
      Error: Invalid theme slug specified. Theme slugs can only contain letters, numbers, underscores and hyphens, and can only start with a letter or underscore.
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

  Scenario: Scaffold tests parses plugin readme.txt
    Given a WP install
    When I run `wp core version`
    Then save STDOUT as {WP_VERSION}
    When I run `wp plugin path`
    Then save STDOUT as {PLUGIN_DIR}

    When I run `wp scaffold plugin hello-world`
    Then STDOUT should not be empty
    And the {PLUGIN_DIR}/hello-world/readme.txt file should exist
    And the {PLUGIN_DIR}/hello-world/.travis.yml file should exist
    And the {PLUGIN_DIR}/hello-world/.travis.yml file should contain:
      """
      env:
        - WP_VERSION=latest WP_MULTISITE=0
        - WP_VERSION=3.7 WP_MULTISITE=0
        - WP_VERSION={WP_VERSION} WP_MULTISITE=0
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

  Scenario: Overwrite existing files
    Given a WP install
    When I run `wp scaffold plugin test`
    And I run `wp scaffold plugin test --force`
    Then STDERR should contain:
    """
    already exists
    """
    And STDOUT should contain:
    """
    Replacing
    """
  Scenario: Scaffold tests for invalid plugin directory
    Given a WP install

    When I try `wp scaffold plugin-tests incorrect-custom-plugin`
    Then STDERR should contain:
      """
      Error: Invalid plugin slug specified.
      """
