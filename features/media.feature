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
      Success: Imported file http://s.wordpress.org/style/images/codeispoetry.png
      """

  Scenario: Fail to import missing image
    Given a WP install

    When I run `wp media import gobbledygook.png`
    Then STDERR should contain:
      """
      Error: Unable to import file gobbledygook.png. Reason: File is empty.
      """

  Scenario: Import a file as attachment from a local image
    Given a WP install
    And a large image file

    When I try to import it
    Then STDOUT should contain:
      """
      Success: Imported file 
      """
    And STDOUT should contain:
      """
      and attached to post 1 as featured image
      """
    And the image should not be deleted
