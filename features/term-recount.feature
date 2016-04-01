Feature: Manage WordPress term recounts

  Background:
    Given a WP install


  Scenario: Term recount with an invalid taxonomy
    When I try `wp term recount some-fake-taxonomy`
    Then STDERR should be:
      """
      Warning: Taxonomy some-fake-taxonomy does not exist.
      """
    