<?php

WP_CLI::add_command( 'scaffold', 'Scaffold_Command' );

/**
 * Implement scaffold command
 *
 * @package wp-cli
 * @subpackage commands/internals
 * @maintainer LinePress (http://www.linespress.org)
 */
class Scaffold_Command extends WP_CLI_Command {
  
  function __construct() {
    WP_Filesystem();
  }

  /**
   * Subcommand posttype
   *
   * @param string $args Name of post type 
   * @param array $assoc_args The ussual WordPress arguments
   *
   */
  function post_type( $args, $assoc_args ) {
    global $wp_filesystem;

    if( !isset( $args[0] ) ) {
      WP_CLI::error( "Please provide a post type name" );
    }

    // Set the args to variables with normal names to keep our sanity
    $post_type                = strtolower( $args[0] );

    // We use the machine name for function declarations 
    $machine_name             = preg_replace( '/-/', '_', $post_type );
    $machine_name_plural      = $this->pluralize( $post_type );

    // If no label is given use the slug and prettify it as good as possible
    if( !isset( $assoc_args['label'] ) ) {
      $label                  = preg_replace( '/_|-/', ' ', strtolower( $post_type ) );
      $label_ucfirst          = ucfirst( $label );
      $label_plural           = $this->pluralize( $label );
      
      $label_plural_ucfirst   = ucfirst( $label_plural );
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
      'rewrite'             => "array( 'slug' => '{$machine_name_plural}', 'feeds' => true, 'pages' => true )",
      'query_var'           => 'true',
      'can_export'          => 'true',
      'context'             => strtolower( wp_get_theme()->template ),
    );

    // Generate the variables from the defaults and associated arguments if they are set
    extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

    $path = TEMPLATEPATH . '/post-types/';
    if( !$wp_filesystem->is_dir( $path ) ) {
      $wp_filesystem->mkdir( $path );
    }

    $filename = $path . $post_type .'.php';

    include 'skeletons/post_type_skeleton.php';

    if ( ! $wp_filesystem->put_contents( $filename, $output ) ) {
      WP_CLI::error( 'Error while saving file' );
    } else {
      WP_CLI::success( $post_type . ' created' );
    }
  }

  /**
   * Subcommand taxonomy
   *
   * @param string $args Name of taxonomy
   * @param array $assoc_args The ussual WordPress arguments
   *
   */
  function taxonomy( $args, $assoc_args ) {
    global $wp_filesystem;

    if( !isset( $args[0] ) ) {
      WP_CLI::error( "Please provide a taxonomy" );
    }

    // Set the args to variables with normal names to keep our sanity
    $taxonomy       = strtolower( $args[0] );

    // We use the machine name for function declarations 
    $machine_name   = preg_replace( '/-/', '_', $taxonomy );

    // If no label is given use the slug and prettify it as good as possible
    if( !isset( $assoc_args['label'] ) ) {
      $label = preg_replace( '/_|-/', ' ', ucfirst( strtolower( $taxonomy ) ) );            
    }

    // Set up defaults and merge theme with assoc_args
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
    
    // Generate the variables from the defaults and associated arguments if they are set
    extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

    $path = TEMPLATEPATH . '/taxonomies/';
    if( !$wp_filesystem->is_dir( $path ) ) {
      $wp_filesystem->mkdir( $path );
    }

    $filename = $path . $taxonomy .'.php';

    include 'skeletons/taxonomy_skeleton.php';

    if ( ! $wp_filesystem->put_contents( $filename, $output ) ) {
      WP_CLI::error( 'Error while saving file' );
    } else {
      WP_CLI::success( $taxonomy . ' created' );
    }
  }

  static function help() {
    WP_CLI::line( 'Welcome to wp-cli scaffold' );
    WP_CLI::line( 'Possible subcommando: post_type, taxonomy' );
    WP_CLI::line( 'Example: post_type zombie' );
    WP_CLI::line( 'Example: taxonomy zombie_speed --post_types=zombie' );
  }

  private function pluralize($word) {
    $plural = array(
    '/(quiz)$/i'                => '\1zes',
    '/^(ox)$/i'                 => '\1en',
    '/([m|l])ouse$/i'           => '\1ice',
    '/(matr|vert|ind)ix|ex$/i'  => '\1ices',
    '/(x|ch|ss|sh)$/i'          => '\1es',
    '/([^aeiouy]|qu)ies$/i'     => '\1y',
    '/([^aeiouy]|qu)y$/i'       => '\1ies',
    '/(hive)$/i'                => '\1s',
    '/(?:([^f])fe|([lr])f)$/i'  => '\1\2ves',
    '/sis$/i'                   => 'ses',
    '/([ti])um$/i'              => '\1a',
    '/(buffal|tomat)o$/i'       => '\1oes',
    '/(bu)s$/i'                 => '1ses',
    '/(alias|status)/i'         => '\1es',
    '/(octop|vir)us$/i'         => '1i',
    '/(ax|test)is$/i'           => '\1es',
    '/s$/i'                     => 's',
    '/$/'                       => 's');

    $uncountable = array('equipment', 'information', 'rice', 'money', 'species', 'series', 'fish', 'sheep');

    $irregular = array(
    'person'    => 'people',
    'man'       => 'men',
    'woman'     => 'women',
    'child'     => 'children',
    'sex'       => 'sexes',
    'move'      => 'moves');

    $lowercased_word = strtolower($word);

    foreach ($uncountable as $_uncountable){
      if(substr($lowercased_word,(-1*strlen($_uncountable))) == $_uncountable){
        return $word;
      }
    }

    foreach ($irregular as $_plural=> $_singular){
      if (preg_match('/('.$_plural.')$/i', $word, $arr)) {
        return preg_replace('/('.$_plural.')$/i', substr($arr[0],0,1).substr($_singular,1), $word);
      }
    }

    foreach ($plural as $rule => $replacement) {
      if (preg_match($rule, $word)) {
        return preg_replace($rule, $replacement, $word);
      }
    }
    return false;

  }
}