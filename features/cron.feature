Feature: Manage WP-Cron events and schedules

  Background:
    Given a WP install

  Scenario: Scheduling and then deleting an event
    When I run `wp cron event schedule wp_cli_test_event_1 '+1 hour'`
    Then STDOUT should contain:
      """
      Success: Scheduled event with hook 'wp_cli_test_event_1'
      """

    When I run `wp cron event list --format=csv --fields=hook,recurrence`
    Then STDOUT should be CSV containing:
      | hook                | recurrence    |
      | wp_cli_test_event_1 | Non-repeating |

    When I run `wp cron event delete wp_cli_test_event_1`
    Then STDOUT should contain:
      """
      Success: Successfully deleted the cron event 'wp_cli_test_event_1'
      """

    When I run `wp cron event list`
    Then STDOUT should not contain:
      """
      wp_cli_test_event_1
      """

  Scenario: Scheduling and then running an event
    When I run `wp cron event schedule wp_cli_test_event_3 '-1 minutes'`
    Then STDOUT should contain:
      """
      Success: Scheduled event with hook 'wp_cli_test_event_3'
      """

    When I run `wp cron event list --format=csv --fields=hook,recurrence`
    Then STDOUT should be CSV containing:
      | hook                | recurrence    |
      | wp_cli_test_event_3 | Non-repeating |

    When I run `wp cron event run wp_cli_test_event_3`
    Then STDOUT should not be empty

    When I run `wp cron event list`
    Then STDOUT should not contain:
      """
      wp_cli_test_event_3
      """

  Scenario: Scheduling and then running a re-occurring event
    When I run `wp cron event schedule wp_cli_test_event_4 now hourly`
    Then STDOUT should contain:
      """
      Success: Scheduled event with hook 'wp_cli_test_event_4'
      """

    When I run `wp cron event list --format=csv --fields=hook,recurrence`
    Then STDOUT should be CSV containing:
      | hook                | recurrence    |
      | wp_cli_test_event_4 | 1 hour        |

    When I run `wp cron event run wp_cli_test_event_4`
    Then STDOUT should not be empty

    When I run `wp cron event list`
    Then STDOUT should contain:
      """
      wp_cli_test_event_4
      """

  Scenario: Scheduling and then deleting a recurring event
    When I run `wp cron event schedule wp_cli_test_event_2 now daily`
    Then STDOUT should contain:
      """
      Success: Scheduled event with hook 'wp_cli_test_event_2'
      """

    When I run `wp cron event list --format=csv --fields=hook,recurrence`
    Then STDOUT should be CSV containing:
      | hook                | recurrence |
      | wp_cli_test_event_2 | 1 day      |

    When I run `wp cron event delete wp_cli_test_event_2`
    Then STDOUT should contain:
      """
      Success: Successfully deleted the cron event 'wp_cli_test_event_2'
      """

    When I run `wp cron event list`
    Then STDOUT should not contain:
      """
      wp_cli_test_event_2
      """

  Scenario: Listing cron schedules
    When I run `wp cron schedule list --format=csv --fields=name,interval`
    Then STDOUT should be CSV containing:
      | name   | interval |
      | hourly | 3600     |

  Scenario: Testing WP-Cron
    When I try `wp cron test`
    Then STDERR should not contain:
      """
      Error:
      """
