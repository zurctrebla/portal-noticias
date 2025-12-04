<?php
/* MUNICIPIOS */

/* POST TYPE */
if (!function_exists('add_municipios')) {
	function add_municipios() 	{
		$labels = array(
			'name' => 'Municípios',
			'menu_name' => 'Municípios',
			'singular_name' => 'Municípios'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'municipios', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'municipios_cat' )
		); 
		register_post_type('municipios', $args);
	}
	add_action( 'init', 'add_municipios' );
}
/**/


/* TAXONOMY CATEGORIES */
if (!function_exists('create_municipios_taxonomies')) {
	function create_municipios_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Municípios', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Municípios' ),
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

		register_taxonomy( 'municipios_cat', array( 'municipios' ), $args );
	}
	add_action( 'init', 'create_municipios_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_municipiosTag_taxonomies')) {
	function create_municipiosTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Municípios' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'municipios_tag', array( 'municipios' ), $args );
	}
	add_action( 'init', 'create_municipiosTag_taxonomies', 0 );
}