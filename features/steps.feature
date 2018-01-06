Feature: Make sure "Given", "When", "Then" steps work as expected

  Scenario: Variable names can only contain uppercase letters, digits and underscores and cannot begin with a digit.

    When I run `echo value`
    And save STDOUT as {VARIABLE_NAME}
    And save STDOUT as {V}
    And save STDOUT as {_VARIABLE_NAME_STARTING_WITH_UNDERSCORE}
    And save STDOUT as {_}
    And save STDOUT as {VARIABLE_NAME_WITH_DIGIT_2}
    And save STDOUT as {V2}
    And save STDOUT as {_2}
    And save STDOUT as {2_VARIABLE_NAME_STARTING_WITH_DIGIT}
    And save STDOUT as {2}
    And save STDOUT as {VARIABLE_NAME_WITH_lowercase}
    And save STDOUT as {v}
    # Note this would give behat "undefined step" message as "save" step uses "\w+"
    #And save STDOUT as {VARIABLE_NAME_WITH_PERCENT_%}

    When I run `echo {VARIABLE_NAME}`
    Then STDOUT should match /^value$/
    And STDOUT should be:
    """
    value
    """

    When I run `echo {V}`
    Then STDOUT should match /^value$/

    When I run `echo {_VARIABLE_NAME_STARTING_WITH_UNDERSCORE}`
    Then STDOUT should match /^value$/

    When I run `echo {_}`
    Then STDOUT should match /^value$/

    When I run `echo {VARIABLE_NAME_WITH_DIGIT_2}`
    Then STDOUT should match /^value$/

    When I run `echo {V2}`
    Then STDOUT should match /^value$/

    When I run `echo {_2}`
    Then STDOUT should match /^value$/

    When I run `echo {2_VARIABLE_NAME_STARTING_WITH_DIGIT}`
    Then STDOUT should match /^\{2_VARIABLE_NAME_STARTING_WITH_DIGIT}$/
    And STDOUT should contain:
    """
    {
    """

    When I run `echo {2}`
    Then STDOUT should match /^\{2}$/

    When I run `echo {VARIABLE_NAME_WITH_lowercase}`
    Then STDOUT should match /^\{VARIABLE_NAME_WITH_lowercase}$/

    When I run `echo {v}`
    Then STDOUT should match /^\{v}$/
