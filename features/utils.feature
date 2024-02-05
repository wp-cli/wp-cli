Feature: Utilities that do NOT depend on WordPress code

  @require-mysql
  Scenario Outline: Check that `proc_open()` and `proc_close()` aren't disabled for `Utils\run_mysql_command()`
    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--ddisable_functions=<func>} --skip-wordpress eval 'WP_CLI\Utils\run_mysql_command( null, array() );'`
    Then STDERR should contain:
      """
      Error: Cannot do 'run_mysql_command': The PHP functions `proc_open()` and/or `proc_close()` are disabled
      """
    And STDOUT should be empty
    And the return code should be 1

    Examples:
      | func       |
      | proc_open  |
      | proc_close |

  Scenario Outline: Check that `proc_open()` and `proc_close()` aren't disabled for `Utils\launch_editor_for_input()`
    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--ddisable_functions=<func>} --skip-wordpress eval 'WP_CLI\Utils\launch_editor_for_input( null, null );'`
    Then STDERR should contain:
      """
      Error: Cannot do 'launch_editor_for_input': The PHP functions `proc_open()` and/or `proc_close()` are disabled
      """
    And STDOUT should be empty
    And the return code should be 1

    Examples:
      | func       |
      | proc_open  |
      | proc_close |

  @require-mysql
  Scenario: Check that `Utils\run_mysql_command()` uses STDOUT and STDERR by default
    When I run `wp --skip-wordpress eval 'WP_CLI\Utils\run_mysql_command( "/usr/bin/env mysql --no-defaults", [ "user" => "{DB_USER}", "pass" => "{DB_PASSWORD}", "host" => "{DB_HOST}", "execute" => "SHOW DATABASES;" ] );'`
    Then STDOUT should contain:
      """
      Database
      """
    And STDOUT should contain:
      """
      information_schema
      """
    And STDERR should be empty

    When I try `wp --skip-wordpress eval 'WP_CLI\Utils\run_mysql_command( "/usr/bin/env mysql --no-defaults", [ "user" => "{DB_USER}", "pass" => "{DB_PASSWORD}", "host" => "{DB_HOST}", "execute" => "broken query" ]);'`
    Then STDOUT should be empty
    And STDERR should contain:
      """
      You have an error in your SQL syntax
      """

  @require-mysql
  Scenario: Check that `Utils\run_mysql_command()` can return data and errors if requested
    When I run `wp --skip-wordpress eval 'list( $stdout, $stderr, $exit_code ) = WP_CLI\Utils\run_mysql_command( "/usr/bin/env mysql --no-defaults", [ "user" => "{DB_USER}", "pass" => "{DB_PASSWORD}", "host" => "{DB_HOST}", "execute" => "SHOW DATABASES;" ], null, false ); fwrite( STDOUT, strtoupper( $stdout ) ); fwrite( STDERR, strtoupper( $stderr ) );'`
    Then STDOUT should not contain:
      """
      Database
      """
    And STDOUT should contain:
      """
      DATABASE
      """
    And STDOUT should not contain:
      """
      information_schema
      """
    And STDOUT should contain:
      """
      INFORMATION_SCHEMA
      """
    And STDERR should be empty

    When I try `wp --skip-wordpress eval 'list( $stdout, $stderr, $exit_code ) = WP_CLI\Utils\run_mysql_command( "/usr/bin/env mysql --no-defaults", [ "user" => "{DB_USER}", "pass" => "{DB_PASSWORD}", "host" => "{DB_HOST}", "execute" => "broken query" ], null, false ); fwrite( STDOUT, strtoupper( $stdout ) ); fwrite( STDERR, strtoupper( $stderr ) );'`
    Then STDOUT should be empty
    And STDERR should not contain:
      """
      You have an error in your SQL syntax
      """
    And STDERR should contain:
      """
      YOU HAVE AN ERROR IN YOUR SQL SYNTAX
      """

  # INI directive `sys_temp_dir` introduced PHP 5.5.0.
  @require-php-5.5
  Scenario: Check `Utils\get_temp_dir()` when `sys_temp_dir` directive set
    # `sys_temp_dir` set to unwritable.
    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--dsys_temp_dir=\\tmp\\} --skip-wordpress eval 'echo WP_CLI\Utils\get_temp_dir();'`
    Then STDERR should contain:
      """
      Warning: Temp directory isn't writable
      """
    And STDERR should contain:
      """
      \tmp/
      """
    And STDOUT should be:
      """
      \tmp/
      """
    And the return code should be 0

    # `sys_temp_dir` unset.
    When I run `{INVOKE_WP_CLI_WITH_PHP_ARGS--dsys_temp_dir=} --skip-wordpress eval 'echo WP_CLI\Utils\get_temp_dir();'`
    Then STDOUT should match /\/$/

  @require-mysql
  Scenario: Ensure that Utils\run_mysql_command() passes through without reading full DB into memory
    Given a WP install

    And I run `printf '%*s' 1048576 | tr ' ' "."`
    And STDOUT should not be empty
    And save STDOUT as {ONE_MB_OF_DATA}
    And a create_sql_file.sh file:
      """
      #!/bin/bash
      echo "CREATE TABLE \`custom_table\` (\`key\` INT(5) UNSIGNED NOT NULL AUTO_INCREMENT, \`text\` LONGTEXT, PRIMARY KEY (\`key\`) );" > test_db.sql
      echo "INSERT INTO \`custom_table\` (\`text\`) VALUES" >> test_db.sql
      index=1
      while [[ $index -le 60 ]];
      do
        echo "('{ONE_MB_OF_DATA}')," >> test_db.sql
        index=`expr $index + 1`
      done
        echo "('{ONE_MB_OF_DATA}');" >> test_db.sql
      """
    And I run `bash create_sql_file.sh`
    And I run `test $(wc -c < test_db.sql) -gt 52428800`
    And a calculate_host_string.sh file:
      """
      #!/bin/bash
      FULL_HOST="{DB_HOST}"
      PORT=""
      HOST_STRING=""
      case ${FULL_HOST##*[]]} in
        (*:*) HOST=${FULL_HOST%:*} PORT=${FULL_HOST##*:};;
        (*)   HOST=$FULL_HOST;;
      esac
      HOST_STRING="--host=$HOST"
      if [ -n "$PORT" ]; then
        HOST_STRING="$HOST_STRING --port=$PORT --protocol=tcp"
      fi
      echo "$HOST_STRING"
      """
    And I run `bash calculate_host_string.sh`
    And STDOUT should contain:
      """
      --host
      """
    And save STDOUT as {DB_HOST_STRING}

    When I try `mysql --database={DB_NAME} --user={DB_ROOT_USER} --password={DB_ROOT_PASSWORD} {DB_HOST_STRING} -e "SET GLOBAL max_allowed_packet=64*1024*1024;"`
    Then the return code should be 0

    # This throws a warning because of the password.
    When I try `mysql --database={DB_NAME} --user={DB_USER} --password={DB_PASSWORD} {DB_HOST_STRING} < test_db.sql`
    Then the return code should be 0

    # The --skip-column-statistics flag is not always present.
    When I try `mysqldump --help | grep -q 'column-statistics' && echo '--skip-column-statistics'`
    Then save STDOUT as {SKIP_COLUMN_STATISTICS_FLAG}

    # This throws a warning because of the password.
    When I try `{INVOKE_WP_CLI_WITH_PHP_ARGS--dmemory_limit=50M -ddisable_functions=ini_set} eval '\WP_CLI\Utils\run_mysql_command("/usr/bin/env mysqldump {SKIP_COLUMN_STATISTICS_FLAG} --no-tablespaces {DB_NAME}", [ "user" => "{DB_USER}", "pass" => "{DB_PASSWORD}", "host" => "{DB_HOST}" ], null, true);'`
    Then the return code should be 0
    And STDOUT should not be empty
    And STDOUT should contain:
      """
      CREATE TABLE
      """
    And STDOUT should contain:
      """
      {ONE_MB_OF_DATA}
      """
