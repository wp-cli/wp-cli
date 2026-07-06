Feature: Extractor

  Scenario: Extracts tar.gz files with long path names
    Given an empty directory

    When I run `wp core download https://downloads.wordpress.org/release/wordpress-7.0.tar.gz --force`
    Then the {RUN_DIR} directory should contain:
    """
    index.php
    license.txt
    """
    And the wp-includes/php-ai-client/src/Providers/Models/TextToSpeechConversion/Contracts/TextToSpee file should not exist
    And the wp-includes/php-ai-client/src/Providers/Models/TextToSpeechConversion/Contracts/TextToSpeechConversionOperationModelInterface.php file should exist
