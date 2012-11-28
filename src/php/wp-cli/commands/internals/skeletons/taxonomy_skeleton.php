<?php
$output = "<?php

if ( !taxonomy_exists( '{$taxonomy}' ) ) :
  \$labels = array( 
    'name'                        =>  __( '{$taxonomy}', '{$textdomain}' ),
    'singular_name'               =>  __( '{$taxonomy}', '{$textdomain}' ),
    'search_items'                =>  __( 'Search {$taxonomy}', '{$textdomain}' ),
    'popular_items'               =>  __( 'Popular {$taxonomy}', '{$textdomain}' ),
    'all_items'                   =>  __( 'All {$taxonomy}', '{$textdomain}' ),
    'parent_item'                 =>  __( 'Parent {$taxonomy}', '{$textdomain}' ),
    'parent_item_colon'           =>  __( 'Parent {$taxonomy}:', '{$textdomain}' ),
    'edit_item'                   =>  __( 'Edit {$taxonomy}', '{$textdomain}' ),
    'update_item'                 =>  __( 'Update {$taxonomy}', '{$textdomain}' ),
    'add_new_item'                =>  __( 'New {$taxonomy}', '{$textdomain}' ),
    'new_item_name'               =>  __( 'New {$taxonomy}', '{$textdomain}' ),
    'separate_items_with_commas'  =>  __( '{$taxonomy}s seperated by comma', '{$textdomain}' ),
    'add_or_remove_items'         =>  __( 'Add or remove {$taxonomy}s', '{$textdomain}' ),
    'choose_from_most_used'       =>  __( 'Choose from the most used {$taxonomy}', '{$textdomain}' ),
    'menu_name'                   =>  __( '{$taxonomy}s', '{$textdomain}' ),
  );

  \$args = array( 
    'labels'                  => \$labels,
    'public'                  => {$public},
    'show_in_nav_menus'       => {$show_in_nav_menus},
    'show_ui'                 => {$show_ui},
    'show_tagcloud'           => {$show_tagcloud},
    'hierarchical'            => {$hierarchical},
    'update_count_callback'   => '_update_post_term_count',
    'rewrite'                 => {$rewrite},
    'query_var'               => {$query_var},
    'capabilities'            => array (
      'manage_terms'  => 'edit_posts',
      'edit_terms'    => 'edit_posts',
      'delete_terms'  => 'edit_posts',
      'assign_terms'  => 'edit_posts'
    ),      
    'rewrite' => array( 
      'slug'          => '{$taxonomy}', 
      'hierarchical'  => {$hierarchical} 
    ),
  );
    
  register_taxonomy( '{$taxonomy}', array( '{$post_types}' ), \$args );

endif;";