Feature: Manage WordPress attachments

  Background:
    Given a WP install

  Scenario: Import image from remote URL
    When I run `wp media import 'http://wp-cli.org/behat-data/codeispoetry.png' --post_id=1`
    Then STDOUT should contain:
      """
      Imported file 'http://wp-cli.org/behat-data/codeispoetry.png'
      """
    And STDOUT should contain:
      """
      Success: Imported 1 of 1 images.
      """

  Scenario: Fail to import missing image
    When I try `wp media import gobbledygook.png`
    Then STDERR should be:
      """
      Warning: Unable to import file 'gobbledygook.png'. Reason: File doesn't exist.
      Error: No images imported.
      """
    And the return code should be 1

  Scenario: Fail to import missing image on Windows
    When I try `wp media import c:/path/gobbledygook.png`
    Then STDERR should be:
      """
      Warning: Unable to import file 'c:/path/gobbledygook.png'. Reason: File doesn't exist.
      Error: No images imported.
      """
    And the return code should be 1

  Scenario: Import a file as attachment from a local image
    Given download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |

    When I run `wp media import {CACHE_DIR}/large-image.jpg --post_id=1 --featured_image`
    Then STDOUT should contain:
      """
      Imported file
      """
    And STDOUT should contain:
      """
      and attached to post 1 as featured image
      """
    And the {CACHE_DIR}/large-image.jpg file should exist
    And the return code should be 0

  Scenario: Import a file as an attachment but porcelain style
    Given download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |

    When I run `wp media import {CACHE_DIR}/large-image.jpg --title="My imported attachment" --caption="My fabulous caption" --porcelain`
    Then save STDOUT as {ATTACHMENT_ID}

    When I run `wp post get {ATTACHMENT_ID} --field=title`
    Then STDOUT should be:
      """
      My imported attachment
      """

    When I run `wp post get {ATTACHMENT_ID} --field=excerpt`
    Then STDOUT should be:
      """
      My fabulous caption
      """

  Scenario: Import a file and use its filename as the title
    Given download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |

    When I run `wp media import {CACHE_DIR}/large-image.jpg --porcelain`
    Then save STDOUT as {ATTACHMENT_ID}

    When I run `wp post get {ATTACHMENT_ID} --field=title`
    Then STDOUT should be:
      """
      large-image
      """

  Scenario: Import a file and persist its original metadata
    Given download:
      | path                         | url                                              |
      | {CACHE_DIR}/canola.jpg       | http://wp-cli.org/behat-data/canola.jpg          |

    When I run `wp media import {CACHE_DIR}/canola.jpg --porcelain`
    Then save STDOUT as {ATTACHMENT_ID}

    When I run `wp post get {ATTACHMENT_ID} --field=title`
    Then STDOUT should be:
      """
      A field of amazing canola
      """

    When I run `wp post get {ATTACHMENT_ID} --field=excerpt`
    Then STDOUT should be:
      """
      The description for the image
      """

  Scenario: Make sure WordPress receives the slashed data it expects
    When I run `wp media import 'http://wp-cli.org/behat-data/codeispoetry.png' --post_id=1 --title='My\Title' --caption='Caption\Here' --alt='Alt\Here' --desc='Desc\Here' --porcelain`
    Then save STDOUT as {ATTACHMENT_ID}

    When I run `wp post get {ATTACHMENT_ID} --format=csv --fields=post_title,post_excerpt,post_content`
    Then STDOUT should contain:
      """
      post_content,"Desc\Here"
      post_title,"My\Title"
      post_excerpt,"Caption\Here"
      """

    When I run `wp post meta get {ATTACHMENT_ID} _wp_attachment_image_alt`
    Then STDOUT should be:
      """
      Alt\Here
      """
