<?php

class Menu_Command extends WP_CLI_Command {
    
    function list_locations() {
        $locations = get_nav_menu_locations();
        WP_CLI::print_value($locations);
        $data = array();
        foreach ($locations as $location=>$id) {
            $data[] = array($id, $location);
        }
        $table = new \cli\Table();
        
        $table->setHeaders( array('Id', 'Location' ) );
        $table->setRows( $data );
        $table->display();
    }
    
    /*
     * Get nav menus
     *  
     * @synopsis 
     */
    function get_nav_menus($args, $assoc_args) {
        $menus = wp_get_nav_menus();
        $data = array();
        foreach ($menus as $menu) {
            $data[] = array($menu->term_id, $menu->slug, $menu->name);
        }
        
        if (isset($assoc_args['json'])) {
            WP_CLI::print_value($data, $assoc_args);
            return;
        }

        $table = new \cli\Table();
        $table->setHeaders( array('Id', 'Slug', 'Name' ) );
        $table->setRows( $data );
        $table->display();
    }
    
    /*
     * Get nav menu items
     * 
     * @synopsis <menu_id> [--json]
     */
    function get_nav_menu_items($args, $assoc_args) {
        $slug  = $args[0];
        $items = wp_get_nav_menu_items($slug);
        WP_CLI::print_value($items, $assoc_args);
    }
    
    /**
     * Create new nav menu
     * 
     * @synopsis <menu_name>
     */
    function create_nav_menu($args, $assoc_args) {
        $menu_name = $args[0];
        $menu = wp_create_nav_menu($menu_name);
        if (is_wp_error($menu)) {
            WP_CLI::error( "Could not create menu '$menu_name'" );
            return;
        }
        WP_CLI::success("Menu created id: " . $menu);
    }

    /**
     * Delete nav menu
     * 
     * @synopsis <menu_id>
     */
    function delete_nav_menu($args, $assoc_args) {
        $menu_slug = $args[0];
        $_menu = wp_get_nav_menu_object($menu_slug);
        if (!$_menu) {
            WP_CLI::error('Menu "' . $menu_slug . '" not found');
            return;
        }
        $ret = wp_delete_nav_menu($_menu->term_id);
        if (is_wp_error($ret)) {
            WP_CLI::error( "Could not delete menu '$_menu->name" );
            return;
        }
        WP_CLI::success("Menu '". $_menu->name. "' deleted");
    }
    
    
    /**
     * Appends new menu item
     *  
     * @synopsis <menu_id> <custom|page|category <item_label>... --<field>=<value>
     */
    function add_menu_item($args, $assoc_args) {
        $menu_slug  = $args[0];
        $item_type  = $args[1];
        $item_label = $args[2];
       
        $allowed_types = array('custom', 'page', 'category');
        if (!in_array($item_type, $allowed_types)) {
            WP_CLI::error('Menu item type should be one of: ' . implode(' ', $allowed_types));
            return;
        }
        
        $_menu = wp_get_nav_menu_object($menu_slug);
        
        if (!$_menu) {
            WP_CLI::error('Menu "' . $menu_slug . '" not found');
            return;
        }
        $params = array();
        foreach ($assoc_args as $k=>$v) {
            $params['menu-' . $k] = $v;
        }
        $params['menu-item-status'] = 'publish';
        $params['menu-item-title']  = $item_label;
        $params['menu-item-label']  = $item_label;
        $params['menu-item-object'] = '';

        switch ($item_type) {
            case 'custom': {
                $params['menu-item-object'] = 'custom';
                if (!isset($assoc_args['custom-url'])) {
                    WP_CLI::error('Custom menu item require --custom-url param');
                    return;
                }
                $params['menu-item-url'] = $assoc_args['custom-url'];
                break;
            }
            case 'page': {
                $params['menu-item-object'] = 'page';
                $params['menu-item-type']   = 'post_type';
                if (!isset($assoc_args['page_slug'])) {
                    WP_CLI::error('Page menu item require --page_slug param');
                    return;
                }
                $page = $page = get_page_by_path($assoc_args['page_slug']);
                if (!$page) {
                    WP_CLI::error('Target page not found');
                }
                unset($params['menu-page_slug']);
                $params['menu-item-object-id'] = $page->ID;
                $params['menu-item-parent-id'] = 0;
                break;
            }
            case 'category': {
                $params['menu-item-object'] = 'category';
                $params['menu-item-type']   = 'taxonomy';
                if (!isset($assoc_args['category_slug'])) {
                    WP_CLI::error('Page menu item require --category_slug param');
                    return;
                }
                $category = get_category_by_slug($assoc_args['category_slug']);
                if (!$category) {
                    WP_CLI::error('Target category not found');
                    return;
                }
                $params['menu-item-object-id'] = $category->cat_ID;
                break;
            }
        }
        
        $new_item = wp_update_nav_menu_item($_menu->term_id, 0, $params);
        if (!$new_item) {
            WP_CLI::error('Failed to create menu item');
            return;
        }

        global $wpdb;
        $wpdb->insert($wpdb->term_relationships, array("object_id" => $new_item, "term_taxonomy_id" => $_menu->term_id), array("%d", "%d"));

        WP_CLI::success("Menu item created");

    }
}

WP_CLI::add_command( 'menu', 'Menu_Command' );

?>
