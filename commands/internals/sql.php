<?php

// Add the command to the wp-cli
WP_CLI::addCommand( 'sql', 'SqlCommand' );

/**
 * Implement sql command
 *
 * @package wp-cli
 * @subpackage commands/internals
 * @maintainer Edgar Marca (http://twitter.com/matiskay)
 **/
class SqlCommand extends WP_CLI_Command {

	protected $default_subcommand = 'cli';

  /**
   * A string for connecting to the DB.
   *
   * @param string $args 
   * @return void
   */
  function connect( $args = array() ) {
    $connect = 'mysql --database=' . DB_NAME . ' --user=' . DB_USER . ' --password=' . DB_PASSWORD; 
    WP_CLI::line( $connect );
  }

  /**
   * Open a SQL command-line interface using Wordpress's credentials. 
   * @param string $args 
   * @return void
   */
  function cli() {
    $exec = sprintf( 'mysql --user=%s --password=%s', DB_USER, DB_PASSWORD );

    proc_close( proc_open( $exec , array( 0 => STDIN, 1 => STDOUT, 2 => STDERR ), $pipes ) );
  } 

  /**
   * Exports the wordpress DB as SQL using mysqldump or equivalent.
   * @param string $args 
   * @return void
   */
  function dump( $args, $assoc_args ) {
    if ( !isset( $assoc_args['file'] ) ) {
      $result_file = sprintf( '%s.sql', DB_NAME );
    } else {
      $result_file = $assoc_args['file'];
    }

    $exec = sprintf( 'mysqldump %s --result-file %s --user=%s --password=%s', DB_NAME, $result_file, DB_USER, DB_PASSWORD );
    exec( $exec );
  } 
  
  /**
   * Execute a query against the site database.
   * @param string $args 
   * @return void
   */

  function query( $args, $assoc_args ) {
    if ( !$args[0] ) {
		  WP_CLI::line( 'Please add a query.' );
    }

    $query = $args[0];
    $connect = 'mysql --database=' . DB_NAME . ' --user=' . DB_USER . ' --password=' . DB_PASSWORD; 
    $exec = sprintf( 'mysql --database=%s  --user=%s --password=%s --execute="%s"', DB_NAME, DB_USER, DB_PASSWORD, $query );
    $result = exec( $exec );
    WP_CLI::line( $result );
  }
}
