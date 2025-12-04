<?php
/* MAIS NOTICIAS */

/* POST TYPE */
if (!function_exists('add_mais_noticias')) {
    function add_mais_noticias() 	{
        $labels = array(
            'name' => 'Mais Notícias',
            'menu_name' => 'Mais Notícias',
            'singular_name' => 'Mais Notícias'
        );
        $args = array(
            'labels' => $labels,
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'query_var' => true,
            'rewrite' => array( 'slug' => 'mais_noticias', 'with_front' => false),
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'supports' => array( 'title','editor', 'author','comments' ),
            'taxonomies' => array( 'mais_noticias_cat' )
        );
        register_post_type('mais_noticias', $args);
    }
    add_action( 'init', 'add_mais_noticias' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_mais_noticias_taxonomies')) {
    function create_mais_noticias_taxonomies() {

        $labels = array(
            'name'              => _x( 'Categorias de Mais Notícias', 'taxonomy general name' ),
            'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
            'search_items'      => __( 'Buscar Categorias' ),
            'all_items'         => __( 'Todas as Categorias' ),
            'edit_item'         => __( 'Editar Categoria' ),
            'add_new_item'      => __( 'Adicionar Categoria' ),
            'new_item_name'     => __( 'Nova Categoria' ),
            'menu_name'         => __( 'Categorias de Mais Notícias' ),
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

        register_taxonomy( 'mais_noticias_cat', array( 'mais_noticias' ), $args );
    }
    add_action( 'init', 'create_mais_noticias_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_mais_noticiasTag_taxonomies')) {
    function create_mais_noticiasTag_taxonomies() {

        $labels = array(
            'name'              => _x( 'Tags', 'taxonomy general name' ),
            'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
            'search_items'      => __( 'Buscar Tags' ),
            'all_items'         => __( 'Todas as Tags' ),
            'edit_item'         => __( 'Editar Tag' ),
            'add_new_item'      => __( 'Adicionar Tag' ),
            'new_item_name'     => __( 'Nova Tag' ),
            'menu_name'         => __( 'Tags de Mais Notícias' ),
        );

        $args = array(
            'hierarchical'      => false,
            'labels'            => $labels,
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'tag' ),
        );

        register_taxonomy( 'mais_noticias_tag', array( 'mais_noticias' ), $args );
    }
    add_action( 'init', 'create_mais_noticiasTag_taxonomies', 0 );
}
