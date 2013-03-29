Feature: Manage WordPress attachments
  
  @images
  Scenario: Regenerate all images while none exists
    Given a WP install

    When I run `wp media regenerate --yes`
    Then STDERR should contain:
      """
      Error: Unable to find the images
      """
