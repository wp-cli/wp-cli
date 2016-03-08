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

    When I run `wp user list --fields=user_login,roles`
    Then STDOUT should be a table containing rows:
      | user_login        | roles      |
      | testuser2         | author     |

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

    When I run `wp user create bobjones bob@example.com --role=author --porcelain`
    And save STDOUT as {BOB_ID}

    And I run `wp user create sally sally@example.com --role=editor --porcelain`
    And save STDOUT as {SALLY_ID}

    When I run `wp post generate --count=3 --post_author=bobjones`
    And I run `wp post list --author={BOB_ID} --format=count`
    Then STDOUT should be:
      """
      3
      """

    When I run `wp user delete bobjones --reassign={SALLY_ID}`
    And I run `wp post list --author={SALLY_ID} --format=count`
    Then STDOUT should be:
      """
      3
      """

  Scenario: Deleting user from the whole network
    Given a WP multisite install

    When I run `wp user create bobjones bob@example.com --role=author --porcelain`
    And save STDOUT as {BOB_ID}

    When I run `wp user get bobjones`
    Then STDOUT should not be empty

    When I run `wp user delete bobjones --network --yes`
    Then STDOUT should not be empty

    When I try `wp user get bobjones`
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

  Scenario: Create new users on multisite
    Given a WP multisite install

    When I try `wp user create bob-jones bobjones@example.com`
    Then STDERR should contain:
      """
      lowercase letters (a-z) and numbers
      """

    When I run `wp user create bobjones bobjones@example.com --display_name="Bob Jones"`
    Then STDOUT should not be empty

    When I run `wp user get bobjones --field=display_name`
    Then STDOUT should be:
      """
      Bob Jones
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

  Scenario: List network users
    Given a WP multisite install

    When I run `wp user create testsubscriber testsubscriber@example.com`
    Then STDOUT should contain:
      """
      Success: Created user
      """

    When I run `wp user list --field=user_login`
    Then STDOUT should contain:
      """
      testsubscriber
      """

    When I run `wp user delete testsubscriber --yes`
    Then STDOUT should contain:
      """
      Success: Removed user
      """

    When I run `wp user list --field=user_login`
    Then STDOUT should not contain:
      """
      testsubscriber
      """

    When I run `wp user list --field=user_login --network`
    Then STDOUT should contain:
      """
      testsubscriber
      """
