Feature: Manage WordPress attachments
  
  @images
  Scenario: Regenerate all images while none exists
    Given a WP install

    When I try `wp media regenerate --yes`
    Then STDERR should contain:
      """
      Error: Unable to find the images
      """

  @images
  Scenario: Import image from remote URL
    Given a WP install

    When I run `wp media import 'http://s.wordpress.org/style/images/codeispoetry.png' --post_id=1`
    Then STDOUT should contain:
      """
      Success: Imported file http://s.wordpress.org/style/images/codeispoetry.png
      """

  @images
  Scenario: Fail to import missing image
    Given a WP install

    When I try `wp media import gobbledygook.png`
    Then STDERR should contain:
      """
      Unable to import file gobbledygook.png. Reason: File doesn't exist.
      """

  @images 
  Scenario: Import a file as attachment from a local image
    Given a WP install
    And download:
      | path                        | url                                                                             |
      | {CACHE_DIR}/large-image.jpg | http://wordpresswallpaper.com/wp-content/gallery/photo-based-wallpaper/1058.jpg |

    When I run `wp media import {CACHE_DIR}/large-image.jpg --post_id=1 --featured_image`
    Then STDOUT should contain:
      """
      Success: Imported file
      """
    And STDOUT should contain:
      """
      and attached to post 1 as featured image
      """
    And the {CACHE_DIR}/large-image.jpg file should exist
