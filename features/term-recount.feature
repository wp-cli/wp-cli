Feature: Manage WordPress term recounts

  Background:
    Given a WP install


  Scenario: Term recount with an invalid taxonomy
    When I try `wp term recount some-fake-taxonomy`
    Then STDERR should be:
      """
      Warning: Taxonomy some-fake-taxonomy does not exist.
      """

  Scenario: Term recount with a valid taxonomy
    When I try `wp term recount category`
    Then STDOUT should be:
      """
      Success: Updated category term count
      """

  Scenario: Term recount with a multiple taxonomies
    When I try `wp term recount category post_tag`
    Then STDOUT should be:
      """
      Success: Updated category term count
      Success: Updated post_tag term count
      """
