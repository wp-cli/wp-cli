Feature: Manage WordPress attachments
  
  @images
  Scenario: Regenerate all images while none exists
    Given a WP install

    When I run `wp media regenerate --yes`
    Then STDERR should contain:
      """
      Error: Unable to find the images
      """

  Scenario: Import image from remote URL
    Given a WP install

	When I run `wp media import 'http://s.wordpress.org/style/images/codeispoetry.png' --post_id=1`
    Then STDOUT should contain:
      """
      Success: Successfully imported file /tmp/codeispoetry.png
      """
