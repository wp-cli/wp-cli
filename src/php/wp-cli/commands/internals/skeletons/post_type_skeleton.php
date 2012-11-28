<?php
$output = "<?php

function {$machine_name}_init() {
  register_post_type( '{$post_type}', 
    array(
      'label'               => __( '{$label_plural_ucfirst}', '{$textdomain}' ),
      'description'         => __( '{$description}', '{$textdomain}' ),
      'public'              => {$public},
      'exclude_from_search' => {$exclude_from_search},
      'show_ui'             => {$show_ui},
      'show_in_nav_menus'   => {$show_in_nav_menus},
      'show_in_menu'        => {$show_in_menu},
      'show_in_admin_bar'   => {$show_in_admin_bar},
      'menu_position'       => {$menu_position},
      'menu_icon'           => {$menu_icon},
      'capability_type'     => '{$capability_type}',
      'hierarchical'        => {$hierarchical},
      'supports'            => array( {$supports} ),
      'has_archive'         => {$has_archive},
      'rewrite'             => array( 'slug' => '{$slug}', 'feeds' => {$feeds}, 'pages' => {$pages} ),
      'query_var'           => {$query_var},
      'can_export'          => {$can_export},
      'labels'              => array(
        'name'                => __( '{$label_plural_ucfirst}', '{$textdomain}' ),
        'singular_name'       => __( '{$label_ucfirst}', '{$textdomain}' ),
        'add_new'             => __( 'Add new {$label}', '{$textdomain}' ),
        'all_items'           => __( '{$label_plural_ucfirst}', '{$textdomain}' ),
        'add_new_item'        => __( 'Add new {$label}', '{$textdomain}' ),
        'edit_item'           => __( 'Edit {$label}', '{$textdomain}' ),
        'new_item'            => __( 'New {$label}', '{$textdomain}' ),
        'view_item'           => __( 'View {$label}', '{$textdomain}' ),
        'search_items'        => __( 'Search {$label_plural}', '{$textdomain}' ),
        'not_found'           => __( 'No {$label_plural} found', '{$textdomain}' ),
        'not_found_in_trash'  => __( 'No {$label_plural} found in trash', '{$textdomain}' ),
        'parent_item_colon'   => __( 'Parent {$label}', '{$textdomain}' ),
        'menu_name'           => __( '{$label_plural_ucfirst}', '{$textdomain}' ),
      ),
    ) 
  );
}
add_action( 'init', '{$machine_name}_init' );

function {$machine_name}_updated_messages( \$messages ) {
  global \$post, \$post_ID;

  \$messages['{$post_type}'] = array(
    0 => '', // Unused. Messages start at index 1.
    1 => sprintf( __('{$label_ucfirst} updated. <a href=\"\">View {$label}</a>', '{$textdomain}'), esc_url( get_permalink(\$post_ID) ) ),
    2 => __('Custom field updated.', '{$textdomain}'),
    3 => __('Custom field deleted.', '{$textdomain}'),
    4 => __('{$label_ucfirst} updated.', '{$textdomain}'),
    /* translators: %s: date and time of the revision */
    5 => isset(\$_GET['revision']) ? sprintf( __('{$label_ucfirst} restored to revision from %s', '{$textdomain}'), wp_post_revision_title( (int) \$_GET['revision'], false ) ) : false,
    6 => sprintf( __('{$label_ucfirst} published. <a href=\"\">View {$label}</a>', '{$textdomain}'), esc_url( get_permalink(\$post_ID) ) ),
    7 => __('{$label_ucfirst} saved.', '{$textdomain}'),
    8 => sprintf( __('{$label_ucfirst} submitted. <a target=\"_blank\" href=\"\">Preview {$post_type}</a>', '{$textdomain}'), esc_url( add_query_arg( 'preview', 'true', get_permalink(\$post_ID) ) ) ),
    9 => sprintf( __('{$label_ucfirst} scheduled for: <strong>%1\$s</strong>. <a target=\"_blank\" href=\"\">Preview {$label}</a>', '{$textdomain}'),
      // translators: Publish box date format, see http://php.net/date
      date_i18n( __( 'M j, Y @ G:i' ), strtotime( \$post->post_date ) ), esc_url( get_permalink( \$post_ID ) ) ),
    10 => sprintf( __('{$label_ucfirst} draft updated. <a target=\"_blank\" href=\"\">Preview {$post_type}</a>', '{$textdomain}'), esc_url( add_query_arg( 'preview', 'true', get_permalink( \$post_ID ) ) ) ),
  );

  return \$messages;
}
add_filter( 'post_updated_messages', '{$machine_name}_updated_messages' );";