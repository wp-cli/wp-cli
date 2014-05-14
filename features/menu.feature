Feature: Manage WordPress menus

  Background:
    Given a WP install

  Scenario: Menu CRUD operations

    When I run `wp menu create "My Menu"`
    And I run `wp menu list --fields=name,slug`
    Then STDOUT should be a table containing rows:
      | name       | slug       |
      | My Menu    | my-menu    |

    When I run `wp menu delete "My Menu"`
    And I run `wp menu list --format=count`
    Then STDOUT should be:
    """
    0
    """

    When I run `wp menu create "First Menu"`
    And I run `wp menu create "Second Menu"`
    And I run `wp menu list --fields=name,slug`
    Then STDOUT should be a table containing rows:
      | name           | slug           |
      | First Menu     | first-menu     |
      | Second Menu    | second-menu    |

    When I run `wp menu delete "First Menu" "Second Menu"`
    And I run `wp menu list --format=count`
    Then STDOUT should be:
    """
    0
    """

  Scenario: Assign / remove location from a menu

    When I run `wp theme install p2 --activate`
    And I run `wp menu location list`
    Then STDOUT should be a table containing rows:
      | location       | description        |
      | primary        | Primary Menu       |

    When I run `wp menu create "Primary Menu"`
    And I run `wp menu location assign primary-menu primary`
    And I run `wp menu list --fields=slug,locations`
    Then STDOUT should be a table containing rows:
      | slug            | locations       |
      | primary-menu    | primary         |

    When I run `wp menu location remove primary-menu primary`
    And I run `wp menu list --fields=slug,locations`
    Then STDOUT should be a table containing rows:
      | slug            | locations       |
      | primary-menu    |                 |

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
    Then STDERR should be empty

    When I run `wp menu item add-term sidebar-menu post_tag {TERM_ID} --porcelain`
    Then save STDOUT as {TERM_ITEM_ID}

    When I run `wp menu item add-custom sidebar-menu Apple http://apple.com --parent-id={POST_ITEM_ID} --porcelain`
    Then save STDOUT as {CUSTOM_ITEM_ID}

    When I run `wp menu item update {CUSTOM_ITEM_ID} --title=WordPress --link='http://wordpress.org' --target=_blank --position=2`
    Then STDERR should be empty

    When I run `wp menu item list sidebar-menu --fields=type,title,description,position,link,menu_item_parent`
    Then STDOUT should be a table containing rows:
      | type      | title            | description       | position | link                 | menu_item_parent |
      | post_type | Custom Test Post | Washington Apples | 1        | {POST_LINK}          | 0                |
      | custom    | WordPress        |                   | 2        | http://wordpress.org | {POST_ITEM_ID}   |
      | taxonomy  | Test term        |                   | 3        | {TERM_LINK}          | 0                |

    When I run `wp menu item delete {CUSTOM_ITEM_ID}`
    And I run `wp menu item list sidebar-menu --format=count`
    Then STDOUT should be:
    """
    2
    """

    When I run `wp menu item delete {POST_ITEM_ID} {TERM_ITEM_ID}`
    And I run `wp menu item list sidebar-menu --format=count`
    Then STDOUT should be:
    """
    0
    """
