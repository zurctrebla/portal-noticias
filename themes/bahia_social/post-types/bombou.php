<?php
/* BAHIA */

/* POST TYPE */
if (!function_exists('add_bombou')) {
	function add_bombou() 	{
		$labels = array(
			'name' => 'Bombou',
			'menu_name' => 'Bombou',
			'singular_name' => 'Bombou'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => false, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'bombou', 'with_front' => false),
			'capability_type' => 'post',
			'has_archive' => true,
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'bombou_cat' )
		); 
		register_post_type('bombou', $args);
	}
	add_action( 'init', 'add_bombou' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_bombou_taxonomies')) {
	function create_bombou_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias Bombou', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Bombou' ),
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

		register_taxonomy( 'bombou_cat', array( 'bombou' ), $args );
	}
	add_action( 'init', 'create_bombou_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_bombouTag_taxonomies')) {
	function create_bombouTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Bombou' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'bombou_tag', array( 'bombou' ), $args );
	}
	add_action( 'init', 'create_bombouTag_taxonomies', 0 );
}