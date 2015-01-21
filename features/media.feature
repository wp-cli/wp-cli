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
    When I run `wp media import 'http://wp-cli.org/behat-data/codeispoetry.png' --post_id=1`
    Then STDOUT should contain:
      """
      Success: Imported file http://wp-cli.org/behat-data/codeispoetry.png
      """

  Scenario: Fail to import missing image
    When I try `wp media import gobbledygook.png`
    Then STDERR should contain:
      """
      Unable to import file gobbledygook.png. Reason: File doesn't exist.
      """

  Scenario: Import a file as attachment from a local image
    Given download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |

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

  Scenario: Import a file as an attachment but porcelain style
    Given download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |

    When I run `wp media import {CACHE_DIR}/large-image.jpg --title="My imported attachment" --porcelain`
    Then save STDOUT as {ATTACHMENT_ID}

    When I run `wp post get {ATTACHMENT_ID} --field=title`
    Then STDOUT should be:
      """
      My imported attachment
      """