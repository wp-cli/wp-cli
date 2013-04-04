Feature: Wordpress code scaffolding
  
  @Custom Post Types
  Scenario: Scaffold a Custom Post Type with label
    Given a WP install

    When I run `wp scaffold post-type zombie --label="Brain eater"`
    Then it should run without errors
    And STDOUT should contain:
      """
      __( 'Brain eaters'
      """

    Scenario: Scaffold a Custom Post Type without the label flag
        Given a WP install

        When I run `wp scaffold post-type zombie`
        Then it should run without errors
        And STDOUT should contain:
          """
          __( 'Zombies'
          """