<?php
  $output = "<?php

  if ( !taxonomy_exists( '{$taxonomy}' ) ) :
    \$labels = array( 
      'name'                        =>  __( '{$taxonomy}', '{$context}' ),
      'singular_name'               =>  __( '{$taxonomy}', '{$context}' ),
      'search_items'                =>  __( 'Search {$taxonomy}', '{$context}' ),
      'popular_items'               =>  __( 'Popular {$taxonomy}', '{$context}' ),
      'all_items'                   =>  __( 'All {$taxonomy}', '{$context}' ),
      'parent_item'                 =>  __( 'Parent {$taxonomy}', '{$context}' ),
      'parent_item_colon'           =>  __( 'Parent {$taxonomy}:', '{$context}' ),
      'edit_item'                   =>  __( 'Edit {$taxonomy}', '{$context}' ),
      'update_item'                 =>  __( 'Update {$taxonomy}', '{$context}' ),
      'add_new_item'                =>  __( 'New {$taxonomy}', '{$context}' ),
      'new_item_name'               =>  __( 'New {$taxonomy}', '{$context}' ),
      'separate_items_with_commas'  =>  __( '{$taxonomy}s seperated by comma', '{$context}' ),
      'add_or_remove_items'         =>  __( 'Add or remove {$taxonomy}s', '{$context}' ),
      'choose_from_most_used'       =>  __( 'Choose from the most used {$taxonomy}', '{$context}' ),
      'menu_name'                   =>  __( '{$taxonomy}s', '{$context}' ),
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