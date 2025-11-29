<?php
/* EXCLUSIVO */

/* POST TYPE */
if (!function_exists('add_exclusivo')) {
	function add_exclusivo() 	{
		$labels = array(
			'name' => 'Exclusivo',
			'menu_name' => 'Exclusivo',
			'singular_name' => 'Exclusivo'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'exclusivo', 'with_front' => false),
			'capability_type' => 'post',
			'has_archive' => true,
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'exclusivo_cat' )
		); 
		register_post_type('exclusivo', $args);
	}
	add_action( 'init', 'add_exclusivo' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_exclusivo_taxonomies')) {
	function create_exclusivo_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias Exclusivo', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Exclusivo' ),
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

		register_taxonomy( 'exclusivo_cat', array( 'exclusivo' ), $args );
	}
	add_action( 'init', 'create_exclusivo_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_exclusivoTag_taxonomies')) {
	function create_exclusivoTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Exclusivo' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'exclusivo_tag', array( 'exclusivo' ), $args );
	}
	add_action( 'init', 'create_exclusivoTag_taxonomies', 0 );
}