Feature: Update core's database

  Scenario: Update db across network
    Given a WP multisite install
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

    When I run `wp core update-db --network`
    Then STDOUT should contain:
      """
      Success: WordPress database upgraded on 3/3 sites.
      """
