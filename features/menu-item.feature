Feature: Manage WordPress menu items

  Background:
    Given a WP install

  Scenario: Add / update / remove items from a menu

    When I run `wp post create --post_title='Test post' --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {POST_ID}

    When I run `wp post url {POST_ID}`
    Then save STDOUT as {POST_LINK}

    When I run `wp term create post_tag 'Test term' --slug=test --description='This is a test term' --porcelain`
    Then STDOUT should be a number
    And save STDOUT as {TERM_ID}

    When I run `wp term url post_tag {TERM_ID}`
    Then save STDOUT as {TERM_LINK}

    When I run `wp menu create "Sidebar Menu"`
    Then STDOUT should not be empty

    When I run `wp menu item add-post sidebar-menu {POST_ID} --title="Custom Test Post" --description="Georgia peaches" --porcelain`
    Then save STDOUT as {POST_ITEM_ID}

    When I run `wp menu item update {POST_ITEM_ID} --description="Washington Apples"`
    Then STDOUT should be:
      """
      Success: Menu item updated.
      """

    When I run `wp menu item add-term sidebar-menu post_tag {TERM_ID} --porcelain`
    Then save STDOUT as {TERM_ITEM_ID}

    When I run `wp menu item add-custom sidebar-menu Apple http://apple.com --parent-id={POST_ITEM_ID} --porcelain`
    Then save STDOUT as {CUSTOM_ITEM_ID}

    When I run `wp menu item update {CUSTOM_ITEM_ID} --title=WordPress --link='http://wordpress.org' --target=_blank --position=2`
    Then STDOUT should be:
      """
      Success: Menu item updated.
      """

    When I run `wp menu item update {TERM_ITEM_ID} --position=3`
    Then STDOUT should be:
      """
      Success: Menu item updated.
      """

    When I run `wp menu item list sidebar-menu --fields=type,title,description,position,link,menu_item_parent`
    Then STDOUT should be a table containing rows:
      | type      | title            | description       | position | link                 | menu_item_parent |
      | post_type | Custom Test Post | Washington Apples | 1        | {POST_LINK}          | 0                |
      | custom    | WordPress        |                   | 2        | http://wordpress.org | {POST_ITEM_ID}   |
      | taxonomy  | Test term        |                   | 3        | {TERM_LINK}          | 0                |

    When I run `wp menu item list sidebar-menu --format=ids`
    Then STDOUT should not be empty

    When I run `wp menu item delete {CUSTOM_ITEM_ID}`
    Then STDOUT should be:
      """
      Success: Deleted 1 of 1 menu items.
      """
    And I run `wp menu item list sidebar-menu --format=count`
    Then STDOUT should be:
      """
      2
      """

    When I run `wp menu item delete {POST_ITEM_ID} {TERM_ITEM_ID}`
    Then STDOUT should be:
      """
      Success: Deleted 2 of 2 menu items.
      """
    And I run `wp menu item list sidebar-menu --format=count`
    Then STDOUT should be:
      """
      0
      """

  Scenario: Preserve grandparent item as ancestor of child item when parent item is removed.

    When I run `wp menu create "Grandparent Test"`
    Then STDOUT should not be empty

    When I run `wp menu item add-custom grandparent-test Grandparent http://example.com/grandparent --porcelain`
    Then save STDOUT as {GRANDPARENT_ID}

    When I run `wp menu item add-custom grandparent-test  Parent   http://example.com/parent   --porcelain  --parent-id={GRANDPARENT_ID}`
    Then save STDOUT as {PARENT_ID}

    When I run `wp menu item add-custom grandparent-test  Child http://example.com/child   --porcelain  --parent-id={PARENT_ID}`
    Then save STDOUT as {CHILD_ID}

    When I run `wp menu item list grandparent-test --fields=title,db_id,menu_item_parent`
    Then STDOUT should be a table containing rows:
      | title       | db_id            | menu_item_parent |
      | Grandparent | {GRANDPARENT_ID} | 0                |
      | Parent      | {PARENT_ID}      | {GRANDPARENT_ID} |
      | Child       | {CHILD_ID}       | {PARENT_ID}      |

    When I run `wp menu item delete {PARENT_ID}`

    When I run `wp menu item list grandparent-test --fields=title,db_id,menu_item_parent`
    Then STDOUT should be a table containing rows:
      | title       | db_id            | menu_item_parent |
      | Grandparent | {GRANDPARENT_ID} | 0                |
      | Child       | {CHILD_ID}       | {GRANDPARENT_ID} |

  Scenario: Error deleting one or more menu items
    When I run `wp menu create "Sidebar Menu"`
    Then STDOUT should not be empty

    When I try `wp menu item delete 99999999`
    Then STDERR should be:
      """
      Warning: Couldn't delete menu item 99999999.
      Error: No menu items deleted.
      """
    And the return code should be 1

    When I run `wp menu item add-custom sidebar-menu Apple http://apple.com --porcelain`
    Then save STDOUT as {CUSTOM_ITEM_ID}

    When I try `wp menu item delete {CUSTOM_ITEM_ID} 99999999`
    Then STDERR should be:
      """
      Warning: Couldn't delete menu item 99999999.
      Error: Only deleted 1 of 2 menu items.
      """
    And the return code should be 1
