Feature: Manage WordPress attachments
  
  Background:
    Given a WP install

  Scenario: Regenerate all images while none exists
    When I try `wp media regenerate --yes`
    Then STDERR should contain:
      """
      No images found.
      """

  Scenario: Import image from remote URL
    When I run `wp media import 'http://s.wordpress.org/style/images/codeispoetry.png' --post_id=1`
    Then STDOUT should contain:
      """
      Success: Imported file http://s.wordpress.org/style/images/codeispoetry.png
      """

  Scenario: Fail to import missing image
    When I try `wp media import gobbledygook.png`
    Then STDERR should contain:
      """
      Unable to import file gobbledygook.png. Reason: File doesn't exist.
      """

  Scenario: Import a file as attachment from a local image
    Given download:
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

  Scenario: Sideload images in post content from a remote domain
    When I run `wp post create --post_title='Post with WordPress.org image' --post_content=' This is a post with an image from WordPress.org <img src="http://s.wordpress.org/style/images/codeispoetry.png" />'`
    Then STDOUT should not be empty

    When I run `wp post list --s='s.wordpress.org' --format=count`
    Then STDOUT should be:
      """
      1
      """

    When I run `wp media sideload --domain='s.wordpress.org'`
    Then STDERR should be empty

    When I run `wp post list --s='s.wordpress.org' --format=count`
    Then STDOUT should be:
      """
      0
      """
