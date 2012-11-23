<?php
/**
 * Implement Scaffold Command
 *
 * @package wp-cli
 * @subpackage commands/community
 * @maintainer LinePress (http://www.linespress.org)
 */
WP_CLI::add_command( 'scaffold', 'Scaffold_Command' );

class Scaffold_Command extends WP_CLI_Command {
  
  /**
   * Subcommand custom post type
   *
   * @param string $args Name of custom post type 
   * @param array $assoc_args
   *
   */

  function custom_post_type( $args, $assoc_args ) {
    if( !isset( $args[0] ) ) WP_CLI::error( "Please provide a cpt name" );

    // set the args to variables with normal names to keep our sanity
    $post_type      = strtolower( $args[0] );
    // we use the machine name for function declarations 
    $machine_name   = preg_replace('/-/', '_', $post_type );

    // if no label is given use the slug and prettify it as good as possible
    if( !isset($assoc_args['label'])){
      $label = preg_replace('/_|-/', ' ', ucfirst( strtolower( $post_type ) ) );            
    }
    
    // set up defaults and merge theme with assoc_args
    $defaults = array(
      'description'         => "",
      'public'              => 'true',
      'exclude_from_search' => 'false',
      'show_ui'             => 'true',
      'show_in_nav_menus'   => 'true',
      'show_in_menu'        => 'true',
      'show_in_admin_bar'   => 'true',
      'menu_position'       => 'null',
      'menu_icon'           => 'null',
      'capability_type'     => 'post',
      'hierarchical'        => 'false',
      'supports'            => "'title', 'editor'",
      'has_archive'         => 'true',
      'rewrite'             => "array( 'slug' => '{$post_type}', 'feeds' => true, 'pages' => true )",
      'query_var'           => 'true',
      'can_export'          => 'true',
      'context'             => strtolower( wp_get_theme()->template ),
    );

    // generate the variables from the defaults and associated arguments if they are set
    extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

    $path = TEMPLATEPATH . '/custom-post-types/';
    if(! is_dir($path) )
      mkdir( $path );

    $file = $path . $post_type .'.php';
    $handle = fopen( $file, 'wb' ) or die( 'Cannot open file:  ' . $file );
  
    include 'skeletons/post_type_skeleton.php';

    fwrite( $handle, $output );
    fclose( $handle );
  }

  /**
   * Subcommand cpt
   * Alias for custom_post_type
   *
   * @param string $args Name of custom post type
   * @param array $assoc_args
   *
   */
  function cpt ( $args, $assoc_args ) {
    $this->custom_post_type( $args, $assoc_args );
  }

  /**
   * Subcommand taxonomy
   *
   * @param string $args Name of taxonomy
   * @param array $assoc_args
   *
   */
  function taxonomy( $args, $assoc_args ) {

    if( !isset($args[0])) WP_CLI::error( "Please provide a taxonomy" );

    // set the args to variables with normal names to keep our sanity
    $taxonomy       = strtolower( $args[0] );

    // we use the machine name for function declarations 
    $machine_name   = preg_replace('/-/', '_', $taxonomy );

    // if no label is given use the slug and prettify it as good as possible
    if( !isset($assoc_args['label'])){
      $label = preg_replace('/_|-/', ' ', ucfirst( strtolower( $taxonomy ) ) );            
    }

    // set up defaults and merge theme with assoc_args
    $defaults = array(
      'public'              => 'true',
      'show_in_nav_menus'   => 'true',
      'show_ui'             => 'true',
      'show_tagcloud'       => 'true',
      'hierarchical'        => 'false',
      'rewrite'             => 'true',
      'query_var'           => 'true',
      'slug'                => $taxonomy,
      'hierarchical'        => 'true',
      'context'             => strtolower( wp_get_theme()->template ),
      'post_types'          => 'post'
    );
    
    // generate the variables from the defaults and associated arguments if they are set
    extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

    $path = TEMPLATEPATH . '/taxonomies/';
    if(! is_dir($path) )
      mkdir( $path );
    
    $file = $path . $taxonomy .'.php';
    $handle = fopen( $file, 'wb' ) or die( 'Cannot open file:  ' . $file );
  
    include 'skeletons/taxonomy_skeleton.php';

     fwrite( $handle, $output );
     fclose( $handle );
  }

  /**
   * Subcommand tax
   * Alias for taxonomy
   *
   * @param string $args Name of taxonomy
   * @param array $assoc_args
   *
   */
  function tax ( $args, $assoc_args ) {
    $this->taxonomy( $args, $assoc_args );
  }

  function status( $args, $assoc_args ) {
    WP_CLI::success( "status command executed \n" );
  }

  static function help() {
    WP_CLI::line( 'Welcome to wp-cli scaffold' );
    WP_CLI::line( 'Possible subcommando: cpt, tax' );
    WP_CLI::line( 'Example: cpt zombie' );
    WP_CLI::line( 'Example: tax zombie_speed --post_types=zombie' );

  }

}