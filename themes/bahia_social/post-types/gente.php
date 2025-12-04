<?php
/* GENTE */

/* POST TYPE */
if (!function_exists('add_gente')) {
	function add_gente() 	{
		$labels = array(
			'name' => 'Gente',
			'menu_name' => 'Gente',
			'singular_name' => 'Gente'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => false, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'gente', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'gente_cat' )
		); 
		register_post_type('gente', $args);
	}
	add_action( 'init', 'add_gente' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_gente_taxonomies')) {
	function create_gente_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Gente', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Gente' ),
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

		register_taxonomy( 'gente_cat', array( 'gente' ), $args );
	}
	add_action( 'init', 'create_gente_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_genteTag_taxonomies')) {
	function create_genteTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Gente' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'gente_tag', array( 'gente' ), $args );
	}
	add_action( 'init', 'create_genteTag_taxonomies', 0 );
}