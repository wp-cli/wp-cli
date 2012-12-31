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
   * @alias cpt
   *
   * @synopsis [--description=<description>] [--public=<public>] [--exclude_from_search=<exclude_from_search>] [--show_ui=<show_ui>] [--show_in_nav_menus=<show_in_nav_menus>] [--show_in_menu=<show_in_menu>] [--show_in_admin_bar=<show_in_admin_bar>] [--menu_position=<menu_position>] [--menu_icon=<menu_icon>] [--capability_type=<capability_type>] [--hierarchical=<hierarchical>] [--supports=<supports>] [--has_archive=<has_archive>] [--slug=<slug>] [--feed=<feed>] [--pages=<pages>] [--query_var=<query_var>] [--can_export=<can_export>] [--textdomain=<textdomain>] [--theme] [--plugin_name=<plugin_name>] [--raw]
   */
  function post_type( $args, $assoc_args ) {
    global $wp_filesystem;

    // Set the args to variables with normal names to keep our sanity
    $post_type                = strtolower( $args[0] );

    // We use the machine name for function declarations
    $machine_name             = preg_replace( '/-/', '_', $post_type );
    $machine_name_plural      = $this->pluralize( $post_type );

    // If no label is given use the slug and prettify it as good as possible
    if( ! isset( $assoc_args['label'] ) ) {
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
      'textdomain'          => '',
      'theme'               => false,
      'plugin_name'         => false,
      'raw'                 => false,
    );

    // Generate the variables from the defaults and associated arguments if they are set
    extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

    $textdomain = $this->get_textdomain( $textdomain, $theme, $plugin_name );

    if( ! $raw ) {
      include 'skeletons/post_type_skeleton.php';
      $output = str_replace( "<?php", "", $output);
      include 'skeletons/post_type_skeleton_extended.php';
    } else {
      include 'skeletons/post_type_skeleton.php';
    }

    if ( $theme || ! empty( $plugin_name ) ) {
      // Write file to theme or given plugin_name
      $assoc_args = array(
        'type' => 'post_type',
        'output' => $output,
        'theme' => $theme,
        'plugin_name' => $plugin_name,
        'machine_name' => $machine_name,
      );
      $assoc_args['path'] = $this->get_output_path( $assoc_args );
      $this->save_skeleton_output( $assoc_args );
    } else {
      // STDOUT
      echo $output;
    }
  }

  /**
   * @subcommand taxonomy
   *
   * @alias tax
   *
   * @synopsis [--public=<public>] [--show_in_nav_menus=<show_in_nav_menus>] [--show_ui=<show_ui>] [--show_tagcloud=<show_tagcloud>] [--hierarchical=<hierarchical>]  [--rewrite=<rewrite>] [--query_var=<query_var>] [--slug=<slug>] [--textdomain=<textdomain>] [--post_types=<post_types>] [--theme] [--plugin_name=<plugin_name>] [--raw]
   */
  function taxonomy( $args, $assoc_args ) {
    global $wp_filesystem;

    // Set the args to variables with normal names to keep our sanity
    $taxonomy       = strtolower( $args[0] );

    // We use the machine name for function declarations
    $machine_name             = preg_replace( '/-/', '_', $taxonomy );
    $machine_name_plural      = $this->pluralize( $taxonomy );

    // If no label is given use the slug and prettify it as good as possible
    if( ! isset( $assoc_args['label'] ) ) {
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
      'post_types'          => 'post',
      'textdomain'          => '',
      'theme'               => false,
      'plugin_name'         => false,
      'raw'                 => false,
    );

    // Generate the variables from the defaults and associated arguments if they are set
    extract( wp_parse_args( $assoc_args, $defaults ), EXTR_SKIP );

    $textdomain = $this->get_textdomain( $textdomain, $theme, $plugin_name );

    if( ! $raw ) {
      include 'skeletons/taxonomy_skeleton.php';
      $output = str_replace( "<?php", "", $output);
      include 'skeletons/taxonomy_skeleton_extended.php';
    } else {
      include 'skeletons/taxonomy_skeleton.php';
    }

    if ( $theme || ! empty( $plugin_name ) ) {
      // Write file to theme or given plugin_name
      $assoc_args = array(
        'type' => 'taxonomy',
        'output' => $output,
        'theme' => $theme,
        'plugin_name' => $plugin_name,
        'machine_name' => $machine_name,
      );
      $assoc_args['path'] = $this->get_output_path( $assoc_args );
      $this->save_skeleton_output( $assoc_args );
    } else {
      // STDOUT
      echo $output;
    }
  }

  private function get_output_path( $assoc_args ) {
    global $wp_filesystem;

    extract( $assoc_args, EXTR_SKIP );

    // Implements the --theme flag || --plugin_name=<plugin_name>
    if( $theme ) {
      //Here we assume you got a theme installed
      $path = TEMPLATEPATH;
    } elseif ( ! empty( $plugin_name ) ){
      $path = WP_PLUGIN_DIR . '/' . $plugin_name; //Faking recursive mkdir for down the line
      $wp_filesystem->mkdir( WP_PLUGIN_DIR . '/' . $plugin_name ); //Faking recursive mkdir for down the line
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
    if( ! $wp_filesystem->is_dir( $path ) ) {
      $wp_filesystem->mkdir( $path );
      WP_CLI::success( "Created dir: {$path}" );
    } elseif( $wp_filesystem->is_dir( $path ) ) {
      WP_CLI::success( "Dir already exists: {$path}" );
    } else {
      WP_CLI::error( "Couldn't create dir exists: {$path}" );
    }

    return $path;
  }

  private function save_skeleton_output( $assoc_args ) {
    global $wp_filesystem;

    extract( $assoc_args, EXTR_SKIP );

    // Write to file
    if( $path ) {
      $filename = $path . $machine_name .'.php';

      if ( ! $wp_filesystem->put_contents( $filename, $output ) ) {
        WP_CLI::error( "Error while saving file: {$filename}" );
      } else {
        WP_CLI::success( "{$type} {$machine_name} created" );
      }
    }
  }

  /**
   * If you're writing your files to your theme directory your textdomain also needs to be the same as your theme.
   * Same goes for when plugin_name is being used.
   */
  private function get_textdomain( $textdomain, $theme, $plugin_name ) {
    if( empty( $textdomain ) && $theme ) {
      $textdomain = strtolower( wp_get_theme()->template );
    } elseif ( empty( $textdomain ) && $plugin_name) {
      $textdomain = $plugin_name;
    } elseif ( empty( $textdomain ) || gettype($textdomain) == 'boolean' ) { //This mean just a flag
      $textdomain = 'YOUR-TEXTDOMAIN';
    }

    return $textdomain;
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
