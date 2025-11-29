<?php
/* ESPECIAL */

/* POST TYPE */
if (!function_exists('add_especial')) {
	function add_especial() 	{
		$labels = array(
			'name' => 'Especial',
			'menu_name' => 'Especial',
			'singular_name' => 'Especial'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'especial', 'with_front' => false),
			'capability_type' => 'post',
			'has_archive' => true,
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'especial_cat' )
		); 
		register_post_type('especial', $args);
	}
	add_action( 'init', 'add_especial' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_especial_taxonomies')) {
	function create_especial_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias Especial', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Especial' ),
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

		register_taxonomy( 'especial_cat', array( 'especial' ), $args );
	}
	add_action( 'init', 'create_especial_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_especialTag_taxonomies')) {
	function create_especialTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Especial' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'especial_tag', array( 'especial' ), $args );
	}
	add_action( 'init', 'create_especialTag_taxonomies', 0 );
}