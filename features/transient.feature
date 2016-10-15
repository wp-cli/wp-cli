Feature: Manage WordPress transient cache

  Scenario: Transient CRUD
    Given a WP install

    When I try `wp transient get foo`
    Then STDERR should be:
      """
      Warning: Transient with key "foo" is not set.
      """

    When I run `wp transient set foo bar`
    Then STDOUT should be:
      """
      Success: Transient added.
      """

    When I run `wp transient get foo`
    Then STDOUT should be:
      """
      bar
      """

    When I run `wp transient delete foo`
    Then STDOUT should be:
      """
      Success: Transient deleted.
      """

  Scenario: Network transient CRUD
    Given a WP multisite install
    And I run `wp site create --slug=foo`

    When I run `wp transient set foo bar --network`
    Then STDOUT should be:
      """
      Success: Transient added.
      """

    When I run `wp --url=example.com/foo transient get foo --network`
    Then STDOUT should be:
      """
      bar
      """

    When I try `wp transient get foo`
    Then STDERR should be:
      """
      Warning: Transient with key "foo" is not set.
      """

    When I run `wp transient delete foo --network`
    Then STDOUT should be:
      """
      Success: Transient deleted.
      """

  Scenario: Transient delete and other flags
    Given a WP install

    When I try `wp transient delete`
    Then STDERR should be:
      """
      Error: Please specify transient key, or use --all or --expired.
      """

    When I run `wp transient set foo bar`
    And I run `wp transient set foo2 bar2`
    And I run `wp transient delete --all`
    Then STDOUT should contain:
      """
      transients deleted from the database.
      """

    When I try `wp transient get foo`
    Then STDERR should be:
      """
      Warning: Transient with key "foo" is not set.
      """

    When I try `wp transient get foo2`
    Then STDERR should be:
      """
      Warning: Transient with key "foo2" is not set.
      """
