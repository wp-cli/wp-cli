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
   * @subcommand post-type
   *
   * @alias pt
   *
   * @synopsis [--description=<description>] [--public=<public>] [--exclude_from_search=<exclude_from_search>] [--show_ui=<show_ui>] [--show_in_nav_menus=<show_in_nav_menus>] [--show_in_menu=<show_in_menu>] [--show_in_admin_bar=<show_in_admin_bar>] [--menu_position=<menu_position>] [--menu_icon=<menu_icon>] [--capability_type=<capability_type>] [--hierarchical=<hierarchical>] [--supports=<supports>] [--has_archive=<has_archive>] [--slug=<slug>] [--feed=<feed>] [--pages=<pages>] [--query_var=<query_var>] [--can_export=<can_export>] [--textdomain=<textdomain>] [--theme] [--plugin=<plugin-name>]
   */
  function post_type( $args, $assoc_args ) {
    global $wp_filesystem;

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
      'slug'                => $machine_name_plural,
      'feeds'               => 'true',
      'pages'               => 'true',
      'query_var'           => 'true',
      'can_export'          => 'true',
      'textdomain'          => strtolower( wp_get_theme()->template ),
      'theme'               => false,
      'plugin'              => false
    );

    // Generate the variables from the defaults and associated arguments if they are set
    extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );
    
    include "skeletons/post_type_skeleton.php";
    $assoc_args = array('type' => 'post_type', 'output' => $output, 'theme' => $theme, 'plugin' => $plugin, 'machine_name' => $machine_name, 'path' => false );
    
    if ( $theme || !empty( $plugin ) ) {
      // Write file to theme or (given) plugin path
      $assoc_args['path'] = $this->get_output_path( $assoc_args );
      $this->parse_skeleton( $assoc_args );
    } else {
      // STDOUT
      echo $this->parse_skeleton( $assoc_args );
    }
  }

  /**
   * @subcommand taxonomy
   *
   * @alias tax
   *
   * @synopsis [--public=<public>] [--show_in_nav_menus=<show_in_nav_menus>] [--show_ui=<show_ui>] [--show_tagcloud=<show_tagcloud>] [--hierarchical=<hierarchical>]  [--rewrite=<rewrite>] [--query_var=<query_var>] [--slug=<slug>] [--textdomain=<textdomain>] [--post_types=<post_types>] [--theme] [--plugin=<plugin-name>]
   */
  function taxonomy( $args, $assoc_args ) {
    global $wp_filesystem;

    // Set the args to variables with normal names to keep our sanity
    $taxonomy       = strtolower( $args[0] );

    // We use the machine name for function declarations 
    $machine_name             = preg_replace( '/-/', '_', $taxonomy );
    $machine_name_plural      = $this->pluralize( $taxonomy );

    // If no label is given use the slug and prettify it as good as possible
    if( !isset( $assoc_args['label'] ) ) {
      $label                  = preg_replace( '/_|-/', ' ', strtolower( $taxonomy ) );
      $label_ucfirst          = ucfirst( $label );
      $label_plural           = $this->pluralize( $label );
      $label_plural_ucfirst   = ucfirst( $label_plural );
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
      'textdomain'          => strtolower( wp_get_theme()->template ),
      'post_types'          => 'post',
      'theme'               => false,
      'plugin'              => false
    );
    
    // Generate the variables from the defaults and associated arguments if they are set
    extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

    include 'skeletons/taxonomy_skeleton.php';
    $assoc_args = array('type' => 'taxonomy', 'output' => $output, 'theme' => $theme, 'plugin' => $plugin, 'machine_name' => $machine_name, 'path' => false );
    
    if ( $theme || !empty( $plugin ) ) {
      // Write file to theme or (given) plugin path
      $assoc_args['path'] = $this->get_output_path( $assoc_args );
      $this->parse_skeleton( $assoc_args );
    } else {
      // STDOUT
      echo $this->parse_skeleton( $assoc_args );
    }
  }

  private function get_output_path( $assoc_args ) {
    global $wp_filesystem;

    extract( $assoc_args, EXTR_SKIP );

    // Implements the --theme flag || --plugin=<plugin-name>
    if( $theme ) {
      //Here we assume you got a theme installed
      $path = TEMPLATEPATH; 
    } elseif ( !empty( $plugin ) ){
      $path = WP_PLUGIN_DIR . '/' . $plugin; //Faking recursive mkdir for down the line
      $wp_filesystem->mkdir( WP_PLUGIN_DIR . '/' . $plugin ); //Faking recursive mkdir for down the line
    } else {
      // STDOUT
      return false;
    }

    if ( $type === "post_type") {
      $path .= '/post-types/';
    } elseif ( $type === "taxonomy" ) {
      $path .= '/taxonomies/';
    }

    // If it doesn't exists create it
    if( !$wp_filesystem->is_dir( $path ) ) {
      $wp_filesystem->mkdir( $path );
      WP_CLI::success( "Created dir: {$path}" );
    } elseif( $wp_filesystem->is_dir( $path ) ) {
      WP_CLI::success( "Dir already exists: {$path}" );
    } else {
      WP_CLI::error( "Couldn't create dir exists: {$path}" );
    }

    return $path;
  }

  private function parse_skeleton( $assoc_args = array() ) {
    global $wp_filesystem;

    extract( $assoc_args, EXTR_SKIP );

    // Write to file
    if( $path ) {
      $filename = $path . $machine_name .'.php';

      if ( ! $wp_filesystem->put_contents( $filename, $output ) ) {
        WP_CLI::error( "Error while saving file: {$filename}" );
      } else {
        WP_CLI::success( ucfirst($type) . " {$machine_name} created" );
      }
    } else {
      // Return for STDOUT
      return $output;
    }
  }

  private function pluralize( $word ) {
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
      '/$/'                       => 's'
    );

    $uncountable = array( 'equipment', 'information', 'rice', 'money', 'species', 'series', 'fish', 'sheep' );

    $irregular = array(
      'person'    => 'people',
      'man'       => 'men',
      'woman'     => 'women',
      'child'     => 'children',
      'sex'       => 'sexes',
      'move'      => 'moves'
    );

    $lowercased_word = strtolower( $word );

    foreach ( $uncountable as $_uncountable ){
      if( substr( $lowercased_word, ( -1 * strlen( $_uncountable) ) ) == $_uncountable ){
        return $word;
      }
    }

    foreach ( $irregular as $_plural=> $_singular ){
      if ( preg_match( '/('.$_plural.')$/i', $word, $arr ) ) {
        return preg_replace( '/('.$_plural.')$/i', substr( $arr[0], 0, 1 ).substr( $_singular, 1 ), $word );
      }
    }

    foreach ( $plural as $rule => $replacement ) {
      if ( preg_match( $rule, $word ) ) {
        return preg_replace( $rule, $replacement, $word );
      }
    }
    return false;
  }
}