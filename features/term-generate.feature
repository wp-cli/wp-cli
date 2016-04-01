Feature: Generate WP terms

  Background:
    Given a WP install

  Scenario: Generating terms and outputting ids
    When I run `wp term generate category --count=1 --format=ids`
    Then save STDOUT as {TERM_ID}

    When I run `wp term update category {TERM_ID} --name="foo"`
    Then STDOUT should contain:
      """
      Success:
      """
