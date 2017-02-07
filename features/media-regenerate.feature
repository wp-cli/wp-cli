Feature: Regenerate WordPress attachments

  Background:
    Given a WP install

  Scenario: Regenerate all images while none exists
    When I try `wp media regenerate --yes`
    Then STDERR should contain:
      """
      No images found.
      """

  Scenario: Delete existing thumbnails when media is regenerated
    Given download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |
    And a wp-content/mu-plugins/media-settings.php file:
      """
      <?php
      add_action( 'after_setup_theme', function(){
        add_image_size( 'test1', 125, 125, true );
      });
      """
    And I run `wp option update uploads_use_yearmonth_folders 0`

    When I run `wp media import {CACHE_DIR}/large-image.jpg --title="My imported attachment" --porcelain`
    Then save STDOUT as {ATTACHMENT_ID}
    And the wp-content/uploads/large-image-125x125.jpg file should exist

    Given a wp-content/mu-plugins/media-settings.php file:
      """
      <?php
      add_action( 'after_setup_theme', function(){
        add_image_size( 'test1', 200, 200, true );
      });
      """
    When I run `wp media regenerate --yes`
    Then STDOUT should contain:
      """
      Success: Regenerated 1 of 1 images.
      """
    And the wp-content/uploads/large-image-125x125.jpg file should not exist
    And the wp-content/uploads/large-image-200x200.jpg file should exist

  Scenario: Skip deletion of existing thumbnails when media is regenerated
    Given download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |
    And a wp-content/mu-plugins/media-settings.php file:
      """
      <?php
      add_action( 'after_setup_theme', function(){
        add_image_size( 'test1', 125, 125, true );
      });
      """
    And I run `wp option update uploads_use_yearmonth_folders 0`

    When I run `wp media import {CACHE_DIR}/large-image.jpg --title="My imported attachment" --porcelain`
    Then save STDOUT as {ATTACHMENT_ID}
    And the wp-content/uploads/large-image-125x125.jpg file should exist

    Given a wp-content/mu-plugins/media-settings.php file:
      """
      <?php
      add_action( 'after_setup_theme', function(){
        add_image_size( 'test1', 200, 200, true );
      });
      """
    When I run `wp media regenerate --skip-delete --yes`
    Then STDOUT should contain:
      """
      Success: Regenerated 1 of 1 images.
      """
    And the wp-content/uploads/large-image-125x125.jpg file should exist
    And the wp-content/uploads/large-image-200x200.jpg file should exist

  Scenario: Provide helpful error messages when media can't be regenerated
    Given download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |
    And a wp-content/mu-plugins/media-settings.php file:
      """
      <?php
      add_action( 'after_setup_theme', function(){
        add_image_size( 'test1', 125, 125, true );
      });
      """
    And I run `wp option update uploads_use_yearmonth_folders 0`

    When I run `wp media import {CACHE_DIR}/large-image.jpg --title="My imported attachment" --porcelain`
    Then save STDOUT as {ATTACHMENT_ID}
    And the wp-content/uploads/large-image-125x125.jpg file should exist

    When I run `rm wp-content/uploads/large-image.jpg`
    Then STDOUT should be empty

    When I try `wp media regenerate --yes`
    Then STDERR should be:
      """
      Warning: Can't find "My imported attachment" (ID {ATTACHMENT_ID}).
      Error: No images regenerated.
      """

  Scenario: Only regenerate images which are missing sizes
    Given download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |
    And a wp-content/mu-plugins/media-settings.php file:
      """
      <?php
      add_action( 'after_setup_theme', function(){
        add_image_size( 'test1', 125, 125, true );
      });
      """
    And I run `wp option update uploads_use_yearmonth_folders 0`

    When I run `wp media import {CACHE_DIR}/large-image.jpg --title="My imported attachment" --porcelain`
    Then save STDOUT as {ATTACHMENT_ID}
    And the wp-content/uploads/large-image-125x125.jpg file should exist

    When I run `wp media import {CACHE_DIR}/large-image.jpg --title="My second imported attachment" --porcelain`
    Then save STDOUT as {ATTACHMENT_ID2}

    When I run `rm wp-content/uploads/large-image-125x125.jpg`
    Then the wp-content/uploads/large-image-125x125.jpg file should not exist

    When I run `wp media regenerate --only-missing --yes`
    Then STDOUT should contain:
      """
      Found 2 images to regenerate.
      """
    And STDOUT should contain:
      """
      1/2 No thumbnail regeneration needed for "My second imported attachment"
      """
    And STDOUT should contain:
      """
      2/2 Regenerated thumbnails for "My imported attachment"
      """
    And STDOUT should contain:
      """
      Success: Regenerated 2 of 2 images
      """

  Scenario: Regenerate images which are missing globally-defined image sizes
    Given download:
      | path                        | url                                              |
      | {CACHE_DIR}/large-image.jpg | http://wp-cli.org/behat-data/large-image.jpg     |
    And I run `wp option update uploads_use_yearmonth_folders 0`

    When I run `wp media import {CACHE_DIR}/large-image.jpg --title="My imported attachment" --porcelain`
    Then save STDOUT as {ATTACHMENT_ID}
    And the wp-content/uploads/large-image-125x125.jpg file should not exist

    Given a wp-content/mu-plugins/media-settings.php file:
      """
      <?php
      add_action( 'after_setup_theme', function(){
        add_image_size( 'test1', 125, 125, true );
      });
      """

    When I run `wp media regenerate --only-missing --yes`
    Then STDOUT should contain:
      """
      Found 1 image to regenerate.
      """
    And STDOUT should contain:
      """
      1/1 Regenerated thumbnails for "My imported attachment"
      """
    And STDOUT should contain:
      """
      Success: Regenerated 1 of 1 images.
      """
    And the wp-content/uploads/large-image-125x125.jpg file should exist

    When I run `wp media regenerate --only-missing --yes`
    Then STDOUT should contain:
      """
      Found 1 image to regenerate
      """
    And STDOUT should contain:
      """
      1/1 No thumbnail regeneration needed for "My imported attachment"
      """
    And STDOUT should contain:
      """
      Success: Regenerated 1 of 1 images.
      """
    And the wp-content/uploads/large-image-125x125.jpg file should exist
