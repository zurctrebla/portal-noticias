<?php
/* ESPORTE */

/* POST TYPE */
if (!function_exists('add_esporte')) {
	function add_esporte() 	{
		$labels = array(
			'name' => 'Esporte',
			'menu_name' => 'Esporte',
			'singular_name' => 'Esporte'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'esporte', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'esporte_cat' )
		); 
		register_post_type('esporte', $args);
	}
	add_action( 'init', 'add_esporte' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_esporte_taxonomies')) {
	function create_esporte_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Esporte', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Esporte' ),
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

		register_taxonomy( 'esporte_cat', array( 'esporte' ), $args );
	}
	add_action( 'init', 'create_esporte_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_esporteTag_taxonomies')) {
	function create_esporteTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Esporte' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'esporte_tag', array( 'esporte' ), $args );
	}
	add_action( 'init', 'create_esporteTag_taxonomies', 0 );
}