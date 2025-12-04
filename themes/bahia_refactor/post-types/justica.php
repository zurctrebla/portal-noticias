<?php
/* JUSTICA */

/* POST TYPE */
if (!function_exists('add_justica')) {
	function add_justica() 	{
		$labels = array(
			'name' => 'Justiça',
			'menu_name' => 'Justiça',
			'singular_name' => 'Justiça'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'justica', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'justica_cat' )
		); 
		register_post_type('justica', $args);
	}
	add_action( 'init', 'add_justica' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_justica_taxonomies')) {
	function create_justica_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Justiça', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Justiça' ),
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

		register_taxonomy( 'justica_cat', array( 'justica' ), $args );
	}
	add_action( 'init', 'create_justica_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_justicaTag_taxonomies')) {
	function create_justicaTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Justiça' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'justica_tag', array( 'justica' ), $args );
	}
	add_action( 'init', 'create_justicaTag_taxonomies', 0 );
}