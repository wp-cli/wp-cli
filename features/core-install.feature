Feature: Install WordPress core

  Scenario: Two WordPress installs sharing the same user table won't update existing user
    Given an empty directory
    And WP files
    And a WP install in 'second'
    And a extra-config file:
      """
      define( 'CUSTOM_USER_TABLE', 'secondusers' );
      define( 'CUSTOM_USER_META_TABLE', 'secondusermeta' );
      """

    When I run `wp --path=second user create testadmin testadmin@example.org --role=administrator`
    Then STDOUT should contain:
      """
      Success: Created user 2.
      """

    When I run `wp --path=second db tables`
    Then STDOUT should contain:
      """
      secondposts
      """
    And STDOUT should contain:
      """
      secondusers
      """

    When I run `wp --path=second user list --field=user_login`
    Then STDOUT should be:
      """
      admin
      testadmin
      """

    When I run `wp --path=second user get testadmin --field=user_pass`
    Then save STDOUT as {ORIGINAL_PASSWORD}

    When I run `wp core config {CORE_CONFIG_SETTINGS} --extra-php < extra-config`
    Then STDOUT should be:
      """
      Success: Generated 'wp-config.php' file.
      """

    When I run `wp core install --url=example.org --title=Test --admin_user=testadmin --admin_email=testadmin@example.com --admin_password=newpassword`
    Then STDOUT should contain:
      """
      Success: WordPress installed successfully.
      """

    When I run `wp user list --field=user_login`
    Then STDOUT should be:
      """
      admin
      testadmin
      """

    When I run `wp user get testadmin --field=email`
    Then STDOUT should be:
      """
      testadmin@example.org
      """

    When I run `wp user get testadmin --field=user_pass`
    Then STDOUT should be:
      """
      {ORIGINAL_PASSWORD}
      """

    When I run `wp db tables`
    Then STDOUT should contain:
      """
      wp_posts
      """
    And STDOUT should contain:
      """
      secondusers
      """
    And STDOUT should not contain:
      """
      wp_users
      """

  Scenario: Two WordPress installs sharing the same user table will create new user
    Given an empty directory
    And WP files
    And a WP install in 'second'
    And a extra-config file:
      """
      define( 'CUSTOM_USER_TABLE', 'secondusers' );
      define( 'CUSTOM_USER_META_TABLE', 'secondusermeta' );
      """

    When I run `wp --path=second db tables`
    Then STDOUT should contain:
      """
      secondposts
      """
    And STDOUT should contain:
      """
      secondusers
      """

    When I run `wp --path=second user list --field=user_login`
    Then STDOUT should be:
      """
      admin
      """

    When I run `wp core config {CORE_CONFIG_SETTINGS} --extra-php < extra-config`
    Then STDOUT should be:
      """
      Success: Generated 'wp-config.php' file.
      """

    When I run `wp core install --url=example.org --title=Test --admin_user=testadmin --admin_email=testadmin@example.com --admin_password=newpassword`
    Then STDOUT should contain:
      """
      Success: WordPress installed successfully.
      """

    When I run `wp user list --field=user_login`
    Then STDOUT should be:
      """
      admin
      testadmin
      """

    When I run `wp --path=second user list --field=user_login`
    Then STDOUT should be:
      """
      admin
      testadmin
      """

    When I run `wp user get testadmin --field=email`
    Then STDOUT should be:
      """
      testadmin@example.com
      """

    When I run `wp db tables`
    Then STDOUT should contain:
      """
      wp_posts
      """
    And STDOUT should contain:
      """
      secondusers
      """
    And STDOUT should not contain:
      """
      wp_users
      """
