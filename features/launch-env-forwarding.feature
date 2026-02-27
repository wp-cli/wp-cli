Feature: Environment variables are forwarded to spawned processes

  In order to avoid configuration and database connection issues
  As a WP-CLI user
  I want WP_CLI::launch() to forward environment variables to child processes

  Background:
    Given an empty directory
    And an env-dump.php file:
      """
      <?php

      // Helper script used by the "launch-env-forwarding" feature.
      // It prints the value of the WPCLI_ENV_FWD environment variable
      // so we can verify what was forwarded into the child process.
      echo getenv( 'WPCLI_ENV_FWD' );
      """

  # Case 1: Normal PHP configuration where `variables_order` includes "E".
  # In this case, the parent shell sets WPCLI_ENV_FWD=ok before launching
  # WP-CLI. WP_CLI::launch() should forward the environment so that the
  # child "php env-dump.php" process can read WPCLI_ENV_FWD via getenv().
  #
  # We use the detailed ProcessRun result and explicitly echo $result->stdout
  # so that Behat can assert on the output from the spawned process.
  Scenario: Forwards environment variables when $_ENV is populated
    When I run `WPCLI_ENV_FWD=ok wp --allow-root --skip-wordpress eval '$result = WP_CLI::launch( "php env-dump.php", true, true ); echo $result->stdout;'`
    Then STDOUT should contain:
      """
      ok
      """

  # Case 2: PHP is started with variables_order=GPCS, so "E" is omitted.
  # This means $_ENV may be empty in the parent process, but the OS-level
  # environment still contains WPCLI_ENV_FWD=ok. When WP_CLI::launch()
  # falls back to passing "null" as the env array, the child process should
  # inherit that environment and still see WPCLI_ENV_FWD=ok.
  #
  # Again, we echo $result->stdout from inside the eval so Behat can assert
  # that the spawned process received the forwarded environment variable.
  Scenario: Still forwards env vars when $_ENV is empty
    When I run `WPCLI_ENV_FWD=ok WP_CLI_PHP_ARGS='-d variables_order=GPCS' wp --allow-root --skip-wordpress eval '$result = WP_CLI::launch( "php env-dump.php", true, true ); echo $result->stdout;'`
    Then STDOUT should contain:
      """
      ok
      """
