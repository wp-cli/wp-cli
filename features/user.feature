Feature: Manage WordPress users

  Scenario: User CRUD operations
    Given a WP install

    When I try `wp user get bogus-user`
    Then the return code should be 1
    And STDOUT should be empty

    When I run `wp user create testuser2 testuser2@example.com --first_name=test --last_name=user --role=author --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {USER_ID}

    When I run `wp user get {USER_ID}`
    Then STDOUT should be a table containing rows:
      | Field        | Value      |
      | ID           | {USER_ID}  |
      | roles        | author     |

    When I run `wp user meta get {USER_ID} first_name`
    Then STDOUT should be:
      """
      test
      """

    When I run `wp user meta get {USER_ID} last_name`
    Then STDOUT should be:
      """
      user
      """

    When I run `wp user delete {USER_ID} --yes`
    Then STDOUT should not be empty

    When I try `wp user create testuser2 testuser2@example.com --role=wrongrole --porcelain`
    Then the return code should be 1
    Then STDOUT should be empty

    When I run `wp user create testuser testuser@example.com --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {USER_ID}

    When I try the previous command again
    Then the return code should be 1

    When I run `wp user update {USER_ID} --display_name=Foo`
    And I run `wp user get {USER_ID}`
    Then STDOUT should be a table containing rows:
      | Field        | Value     |
      | ID           | {USER_ID} |
      | display_name | Foo       |

    When I run `wp user get testuser@example.com`
    Then STDOUT should be a table containing rows:
      | Field        | Value     |
      | ID           | {USER_ID} |
      | display_name | Foo       |

    When I run `wp user delete {USER_ID} --yes`
    Then STDOUT should not be empty

  Scenario: Reassigning user posts
    Given a WP multisite install

    When I run `wp user create bob bob@example.com --role=author --porcelain`
    And save STDOUT as {BOB_ID}

    And I run `wp user create sally sally@example.com --role=editor --porcelain`
    And save STDOUT as {SALLY_ID}

    When I run `wp post generate --count=3 --post_author=bob`
    And I run `wp post list --author={BOB_ID} --format=count`
    Then STDOUT should be:
      """
      3
      """

    When I run `wp user delete bob --reassign={SALLY_ID}`
    And I run `wp post list --author={SALLY_ID} --format=count`
    Then STDOUT should be:
      """
      3
      """

  Scenario: Deleting user from the whole network
    Given a WP multisite install

    When I run `wp user create bob bob@example.com --role=author --porcelain`
    And save STDOUT as {BOB_ID}

    When I run `wp user get bob`
    Then STDOUT should not be empty

    When I run `wp user delete bob --network --yes`
    Then STDOUT should not be empty

    When I try `wp user get bob`
    Then STDERR should not be empty

  Scenario: Generating and deleting users
    Given a WP install

    When I run `wp user list --role=editor --format=count`
    Then STDOUT should be:
      """
      0
      """

    When I run `wp user generate --count=10 --role=editor`
    And I run `wp user list --role=editor --format=count`
    Then STDOUT should be:
      """
      10
      """

    When I try `wp user list --field=ID | xargs wp user delete invalid-user --yes`
    And I run `wp user list --format=count`
    Then STDOUT should be:
      """
      0
      """

  Scenario: Importing users from a CSV file
    Given a WP install
    And a users.csv file:
      """
      user_login,user_email,display_name,role
      bobjones,bobjones@domain.com,Bob Jones,contributor
      newuser1,newuser1@domain.com,New User,author
      admin,admin@domain.com,Existing User,administrator
      """

    When I try `wp user import-csv users-incorrect.csv --skip-update`
    Then STDERR should be:
      """
      Error: Missing file: users-incorrect.csv
      """

    When I try `wp user import-csv http://example.com/users.csv --skip-update`
    Then STDERR should be:
      """
      Error: Couldn't access remote CSV file (HTTP 404 response).
      """

    When I run `wp user import-csv users.csv`
    Then STDOUT should not be empty

    When I run `wp user list --format=count`
    Then STDOUT should be:
      """
      3
      """

    When I run `wp user list --format=json`
    Then STDOUT should be JSON containing:
      """
      [{
        "user_login":"admin",
        "display_name":"Existing User",
        "user_email":"admin@domain.com",
        "roles":"administrator"
      }]
      """

  Scenario: Import new users but don't update existing
    Given a WP install
    And a users.csv file:
      """
      user_login,user_email,display_name,role
      bobjones,bobjones@domain.com,Bob Jones,contributor
      newuser1,newuser1@domain.com,New User,author
      admin,admin@domain.com,Existing User,administrator
      """

    When I run `wp user create bobjones bobjones@domain.com --display_name="Robert Jones" --role=administrator`
    Then STDOUT should not be empty

    When I run `wp user import-csv users.csv --skip-update`
    Then STDOUT should not be empty

    When I run `wp user list --format=count`
    Then STDOUT should be:
      """
      3
      """

    When I run `wp user get bobjones --fields=user_login,display_name,user_email,roles --format=json`
    Then STDOUT should be JSON containing:
      """
      {
        "user_login":"bobjones",
        "display_name":"Robert Jones",
        "user_email":"bobjones@domain.com",
        "roles":"administrator"
      }
      """

  Scenario: Managing user roles
    Given a WP install

    When I run `wp user add-role 1 editor`
    Then STDOUT should not be empty
    And I run `wp user get 1 --field=roles`
    Then STDOUT should be:
      """
      administrator, editor
      """

    When I try `wp user add-role 1 edit`
    Then STDERR should contain:
      """
      Role doesn't exist
      """

    When I try `wp user set-role 1 edit`
    Then STDERR should contain:
      """
      Role doesn't exist
      """

    When I try `wp user remove-role 1 edit`
    Then STDERR should contain:
      """
      Role doesn't exist
      """

    When I run `wp user set-role 1 author`
    Then STDOUT should not be empty
    And I run `wp user get 1`
    Then STDOUT should be a table containing rows:
      | Field | Value  |
      | roles | author |

    When I run `wp user remove-role 1 editor`
    Then STDOUT should not be empty
    And I run `wp user get 1`
    Then STDOUT should be a table containing rows:
      | Field | Value  |
      | roles | author |

    When I run `wp user remove-role 1`
    Then STDOUT should not be empty
    And I run `wp user get 1`
    Then STDOUT should be a table containing rows:
      | Field | Value |
      | roles |       |
      
  Scenario: Managing user capabilities
    Given a WP install

    When I run `wp user add-cap 1 edit_vip_product`
    Then STDOUT should be:
      """
      Success: Added 'edit_vip_product' capability for admin (1).
      """
      
    And I run `wp user list-caps 1 | tail -n 1`
    Then STDOUT should be:
      """
      edit_vip_product
      """
      
    And I run `wp user remove-cap 1 edit_vip_product`
    Then STDOUT should be:
      """
      Success: Removed 'edit_vip_product' cap for admin (1).
      """

  Scenario: Show password when creating a user
    Given a WP install

    When I run `wp user create testrandompass testrandompass@example.com`
    Then STDOUT should contain:
       """
       Password:
       """

    When I run `wp user create testsuppliedpass testsuppliedpass@example.com --user_pass=suppliedpass`
    Then STDOUT should not contain:
       """
       Password:
       """
