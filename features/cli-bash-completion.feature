Feature: `wp cli completions` tasks

  Scenario: Bash Completion without wp-cli.yml
    Given an empty directory

    When I run `wp cli completions --line="wp " --point=100`
    Then STDOUT should contain:
      """
      config
      """
    And STDOUT should contain:
      """
      core
      """
    And STDOUT should contain:
      """
      eval
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp co" --point=100`
    Then STDOUT should contain:
      """
      config
      """
    And STDOUT should contain:
      """
      core
      """
    And STDOUT should not contain:
      """
      eval
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp core " --point=100`
    Then STDOUT should contain:
      """
      install
      """
    And STDOUT should contain:
      """
      update
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp help " --point=100`
    Then STDOUT should contain:
      """
      config
      """
    And STDOUT should contain:
      """
      core
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp help core " --point=100`
    Then STDOUT should contain:
      """
      install
      """
    And STDOUT should contain:
      """
      update
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp core" --point=100`
    Then STDOUT should contain:
      """
      core
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp core " --point=100`
    Then STDOUT should contain:
      """
      download
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line='wp bogus-comand ' --point=100`
    Then STDOUT should be empty

    When I run `wp cli completions --line='wp eva' --point=100`
    Then STDOUT should contain:
      """
      eval
      """
    And STDOUT should contain:
      """
      eval-file
      """

    When I run `wp cli completions --line='wp config create --dbname=' --point=100`
    Then STDOUT should be empty

    When I run `wp cli completions --line='wp config create --dbname=foo ' --point=100`
    Then STDOUT should not contain:
      """
      --dbname=
      """
    And STDOUT should contain:
      """
      --extra-php
      """

    When I run `wp cli completions --line='wp eval-file ' --point=100`
    Then STDOUT should contain:
      """
      <file>
      """

  Scenario: Bash Completion with SSH aliases
    Given an empty directory
    And a wp-cli.yml file:
      """
      @example:
        ssh: example.com
      """

    When I run `wp cli completions --line="wp " --point=100`
    Then STDOUT should contain:
      """
      @example
      """
    And STDOUT should contain:
      """
      config
      """
    And STDOUT should contain:
      """
      core
      """
    And STDOUT should contain:
      """
      eval
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp @e" --point=100`
    Then STDOUT should contain:
      """
      @example
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp @example " --point=100`
    Then STDOUT should not contain:
      """
      @example
      """
    And STDOUT should contain:
      """
      config
      """
    And STDOUT should contain:
      """
      core
      """
    And STDOUT should contain:
      """
      eval
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp @example core " --point=100`
    Then STDOUT should contain:
      """
      install
      """
    And STDOUT should contain:
      """
      update
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp help core " --point=100`
    Then STDOUT should contain:
      """
      install
      """
    And STDOUT should contain:
      """
      update
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp help " --point=100`
    Then STDOUT should not contain:
      """
      @example
      """
    And STDOUT should contain:
      """
      config
      """
    And STDOUT should contain:
      """
      core
      """
    And STDOUT should contain:
      """
      eval
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp help core" --point=100`
    Then STDOUT should contain:
      """
      core
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp help core " --point=100`
    Then STDOUT should contain:
      """
      download
      """
    And STDERR should be empty
    And the return code should be 0

  Scenario: Bash Completion for global parameters
    Given an empty directory

    When I run `wp cli completions --line="wp core download " --point=100`
    Then STDOUT should contain:
      """
      --path=
      """
    And STDOUT should contain:
      """
      --ssh=
      """
    And STDOUT should contain:
      """
      --http=
      """
    And STDOUT should contain:
      """
      --url=
      """
    And STDOUT should contain:
      """
      --user=
      """
    And STDOUT should contain:
      """
      --skip-plugins=
      """
    And STDOUT should contain:
      """
      --skip-themes=
      """
    And STDOUT should contain:
      """
      --skip-packages
      """
    And STDOUT should contain:
      """
      --require=
      """
    And STDOUT should contain:
      """
      --color
      """
    And STDOUT should contain:
      """
      --no-color
      """
    And STDOUT should contain:
      """
      --debug=
      """
    And STDOUT should contain:
      """
      --prompt=
      """
    And STDOUT should contain:
      """
      --quiet
      """
    And STDOUT should not contain:
      """
      --skip-packages=
      """
    And STDOUT should not contain:
      """
      --color=
      """
    And STDOUT should not contain:
      """
      --quiet=
      """
    And STDERR should be empty
    And the return code should be 0

    When I run `wp cli completions --line="wp core download --path --p" --point=100`
    Then STDOUT should contain:
      """
      --prompt=
      """
    Then STDOUT should not contain:
      """
      --path
      """

    When I run `wp cli completions --line="wp core download --no-color" --point=100`
    Then STDOUT should not contain:
      """
      --no-color
      """

 Scenario: Bash Completion for global --url parameter
    Given a WP multisite installation
    And I run `wp site create --slug=foo.example.org`
    And I run `wp site create --slug=foot.example.org`
    And I run `wp site create --slug=football.example.org`
    And I run `wp site create --slug=bar.example.org/quix`
    And I run `wp site create --slug=bar.example.org/quiz`
    And I run `wp site create --slug=waldo.example.org`

    # show all matches
    When I run `wp cli completions --line="wp plugin list --url=foo"
    Then STDOUT should contain:
      """
      foo.example.org       foot.example.org      football.example.org
      """

    # autocomplete up to where the matches diverge
    # todo probably needs to check in a different way than the others?
    # todo not necessary if cant autocomplete when multiple
    When I run `wp cli completions --line="wp plugin list --url=bar"
        Then STDOUT should contain:
          """
          bar.example.org/qui
          """

    # autocomplete entirely when only 1 match
    When I run `wp cli completions --line="wp plugin list --url=wal"
            Then STDOUT should contain:
              """
              waldo.example.org
              """
