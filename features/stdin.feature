Feature: Parse CSV from STDIN or file

  Scenario: Import user from CSV
    Given a WP install
    And a users.csv file:
    """
    user_login,user_email,display_name,role
    bobjones,bobjones@example.com,Bob Jones,contributor
    newuser1,newuser1@example.com,New User,author
    existinguser,existinguser@example.com,Existing User,administrator
    """

    When I run `wp user import-csv users.csv`
    Then STDOUT should be:
      """
      Success: bobjones created.
      Success: newuser1 created.
      Success: existinguser created.
      """
    And STDERR should be empty

    When I run `wp user get bobjones`
    Then the return code should be 0
    And STDERR should be empty

    When I run `wp user get newuser1`
    Then the return code should be 0
    And STDERR should be empty

    When I run `wp user get existinguser`
    Then the return code should be 0
    And STDERR should be empty

  Scenario: Import user from STDIN
    Given a WP install
    And a users.csv file:
    """
    user_login,user_email,display_name,role
    stdinuser1,bobjones@example.com,Bob Jones,contributor
    stdinuser2,newuser1@example.com,New User,author
    existinguser,existinguser@example.com,Existing User,administrator
    """

    When I run `cat users.csv | wp user import-csv -`
    Then STDOUT should be:
      """
      Success: stdinuser1 created.
      Success: stdinuser2 created.
      Success: existinguser created.
      """
    And STDERR should be empty

    When I run `wp user get stdinuser1`
    Then the return code should be 0
    And STDERR should be empty

    When I run `wp user get stdinuser2`
    Then the return code should be 0
    And STDERR should be empty

    When I run `wp user get existinguser`
    Then the return code should be 0
    And STDERR should be empty
