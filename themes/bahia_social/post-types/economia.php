<?php

/* ECONOMIA */

/* POST TYPE */
if (!function_exists('add_economia')) {

    function add_economia() {
        $labels = array(
            'name' => 'Economia',
            'menu_name' => 'Economia',
            'singular_name' => 'Economia'
        );
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array('slug' => _x('economia', 'URL slug', 'your_text_domain')),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'supports' => array('title', 'editor', 'author', 'comments'),
            'taxonomies' => array('economia_cat')
        );
        register_post_type('economia', $args);
    }

    add_action('init', 'add_economia');
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_economia_taxonomies')) {

    function create_economia_taxonomies() {

        $labels = array(
            'name' => _x('Categorias de Economia', 'taxonomy general name'),
            'singular_name' => _x('Categoria', 'taxonomy singular name'),
            'search_items' => __('Buscar Categorias'),
            'all_items' => __('Todas as Categorias'),
            'edit_item' => __('Editar Categoria'),
            'add_new_item' => __('Adicionar Categoria'),
            'new_item_name' => __('Nova Categoria'),
            'menu_name' => __('Categorias de Economia'),
        );

        $show_ui = true;

        $args = array(
            'hierarchical' => true,
            'labels' => $labels,
            'show_ui' => $show_ui,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'categoria'),
        );

        register_taxonomy('economia_cat', array('economia'), $args);
    }

    add_action('init', 'create_economia_taxonomies', 0);
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_economiaTag_taxonomies')) {

    function create_economiaTag_taxonomies() {

        $labels = array(
            'name' => _x('Tags', 'taxonomy general name'),
            'singular_name' => _x('Tag', 'taxonomy singular name'),
            'search_items' => __('Buscar Tags'),
            'all_items' => __('Todas as Tags'),
            'edit_item' => __('Editar Tag'),
            'add_new_item' => __('Adicionar Tag'),
            'new_item_name' => __('Nova Tag'),
            'menu_name' => __('Tags de Economia'),
        );

        $args = array(
            'hierarchical' => false,
            'labels' => $labels,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => array('slug' => 'tag'),
        );

        register_taxonomy('economia_tag', array('economia'), $args);
    }

    add_action('init', 'create_economiaTag_taxonomies', 0);
}