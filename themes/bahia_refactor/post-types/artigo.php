<?php
/* BAHIA */

/* POST TYPE */
if (!function_exists('add_artigo')) {
    function add_artigo() {
        $labels = array(
            'name' => 'Artigos',
            'menu_name' => 'Artigos',
            'singular_name' => 'Artigo'
        );
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array( 'slug' => 'artigo', 'with_front' => false),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'supports' => array( 'title','editor', 'author','comments' ),
            'taxonomies' => array( 'artigo_cat' )
        );
        register_post_type('artigo', $args);
    }
    add_action( 'init', 'add_artigo' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_artigo_taxonomies')) {
    function create_artigo_taxonomies() {
        $labels = array(
            'name'          => _x( 'Categorias de Artigos', 'taxonomy general name' ),
            'singular_name' => _x( 'Categoria', 'taxonomy singular name' ),
            'search_items'  => __( 'Buscar Categorias' ),
            'all_items'     => __( 'Todas as Categorias' ),
            'edit_item'     => __( 'Editar Categoria' ),
            'add_new_item'  => __( 'Adicionar Categoria' ),
            'new_item_name' => __( 'Nova Categoria' ),
            'menu_name'     => __( 'Categorias de Artigos' ),
        );

        $show_ui = true;

        $args = array(
            'hierarchical'      => true,
            'labels'            => $labels,
            'show_ui'           => $show_ui,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'categoria' ),
        );
        register_taxonomy('artigo_cat', array( 'artigo' ), $args);
    }

    add_action('init', 'create_artigo_taxonomies', 0);
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_artigoTag_taxonomies')) {
    function create_artigoTag_taxonomies() {

        $labels = array(
            'name'              => _x( 'Tags', 'taxonomy general name' ),
            'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
            'search_items'      => __( 'Buscar Tags' ),
            'all_items'         => __( 'Todas as Tags' ),
            'edit_item'         => __( 'Editar Tag' ),
            'add_new_item'      => __( 'Adicionar Tag' ),
            'new_item_name'     => __( 'Nova Tag' ),
            'menu_name'         => __( 'Tags de Artigos' ),
        );

        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'tag' ),
        );
        register_taxonomy('artigo_tag', array( 'artigo' ), $args);
    }

    add_action('init', 'create_artigoTag_taxonomies', 0);
}
