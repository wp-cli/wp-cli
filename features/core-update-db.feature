Feature: Update core's database

  Scenario: Update db on a single site
    Given a WP install
    And I run `wp core download --version=4.1 --force`
    And I run `wp option update db_version 29630`

    When I run `wp core update-db`
    Then STDOUT should contain:
      """
      Success: WordPress database upgraded successfully from db version 29630 to 30133.
      """

    When I run `wp core update-db`
    Then STDOUT should contain:
      """
      Success: WordPress database already at latest db version 30133.
      """

  Scenario: Dry run update db on a single site
    Given a WP install
    And I run `wp core download --version=4.1 --force`
    And I run `wp option update db_version 29630`

    When I run `wp core update-db --dry-run`
    Then STDOUT should be:
      """
      Performing a dry run, with no database modification.
      Success: WordPress database will be upgraded from db version 29630 to 30133.
      """

    When I run `wp option get db_version`
    Then STDOUT should be:
      """
      29630
      """

  Scenario: Update db across network
    Given a WP multisite install
    And I run `wp core download --version=4.1 --force`
    And I run `wp option update db_version 29630`
    And I run `wp site option update wpmu_upgrade_site 29630`
    And I run `wp site create --slug=foo`
    And I run `wp site create --slug=bar`
    And I run `wp site create --slug=burrito --porcelain`
    And save STDOUT as {BURRITO_ID}
    And I run `wp site create --slug=taco --porcelain`
    And save STDOUT as {TACO_ID}
    And I run `wp site create --slug=pizza --porcelain`
    And save STDOUT as {PIZZA_ID}
    And I run `wp site archive {BURRITO_ID}`
    And I run `wp site spam {TACO_ID}`
    And I run `wp site delete {PIZZA_ID} --yes`

    When I run `wp site option get wpmu_upgrade_site`
    Then save STDOUT as {UPDATE_VERSION}

    When I run `wp core update-db --network`
    Then STDOUT should contain:
      """
      Success: WordPress database upgraded on 3/3 sites.
      """

    When I run `wp site option get wpmu_upgrade_site`
    Then STDOUT should not contain:
      """
      {UPDATE_VERSION}
      """

  Scenario: Update db across network, dry run
    Given a WP multisite install
    And I run `wp core download --version=4.1 --force`
    And I run `wp option update db_version 29630`
    And I run `wp site option update wpmu_upgrade_site 29630`
    And I run `wp site create --slug=foo`
    And I run `wp site create --slug=bar`
    And I run `wp site create --slug=burrito --porcelain`
    And save STDOUT as {BURRITO_ID}
    And I run `wp site create --slug=taco --porcelain`
    And save STDOUT as {TACO_ID}
    And I run `wp site create --slug=pizza --porcelain`
    And save STDOUT as {PIZZA_ID}
    And I run `wp site archive {BURRITO_ID}`
    And I run `wp site spam {TACO_ID}`
    And I run `wp site delete {PIZZA_ID} --yes`

    When I run `wp site option get wpmu_upgrade_site`
    Then save STDOUT as {UPDATE_VERSION}

    When I run `wp core update-db --network --dry-run`
    Then STDOUT should contain:
      """
      Performing a dry run, with no database modification.
      """
    And STDOUT should contain:
      """
      WordPress database will be upgraded from db version
      """
    And STDOUT should not contain:
      """
      WordPress database upgraded successfully from db version
      """
    And STDOUT should contain:
      """
      Success: WordPress database upgraded on 3/3 sites.
      """

    When I run `wp site option get wpmu_upgrade_site`
    Then STDOUT should contain:
      """
      {UPDATE_VERSION}
      """

  Scenario: Ensure update-db sets WP_INSTALLING constant
    Given a WP install
    And a before.php file:
      """
      <?php
      WP_CLI::add_hook( 'before_invoke:core update-db', function(){
        WP_CLI::log( 'WP_INSTALLING: ' . var_export( WP_INSTALLING, true ) );
      });
      """

    When I run `wp --require=before.php core update-db`
    Then STDOUT should contain:
      """
      WP_INSTALLING: true
      """
