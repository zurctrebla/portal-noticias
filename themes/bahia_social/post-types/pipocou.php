<?php
/* PIPOCOU */

/* POST TYPE */
if (!function_exists('add_pipocou')) {
	function add_pipocou() 	{
		$labels = array(
			'name' => 'Pipocou',
			'menu_name' => 'Pipocou',
			'singular_name' => 'Pipocou'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'pipocou', 'with_front' => false),
			'capability_type' => 'post',
			'has_archive' => true,
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'pipocou_cat' )
		); 
		register_post_type('pipocou', $args);
	}
	add_action( 'init', 'add_pipocou' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_pipocou_taxonomies')) {
	function create_pipocou_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias Pipocou', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Pipocou' ),
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

		register_taxonomy( 'pipocou_cat', array( 'pipocou' ), $args );
	}
	add_action( 'init', 'create_pipocou_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_pipocouTag_taxonomies')) {
	function create_pipocouTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Pipocou' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'pipocou_tag', array( 'pipocou' ), $args );
	}
	add_action( 'init', 'create_pipocouTag_taxonomies', 0 );
}