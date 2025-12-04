<?php
/* BAHIA */

/* POST TYPE */
if (!function_exists('add_bahia')) {
	function add_bahia() 	{
		$labels = array(
			'name' => 'Bahia',
			'menu_name' => 'Bahia',
			'singular_name' => 'Bahia'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'bahia', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'bahia_cat' )
		); 
		register_post_type('bahia', $args);
	}
	add_action( 'init', 'add_bahia' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_bahia_taxonomies')) {
	function create_bahia_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Bahia', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Bahia' ),
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

		register_taxonomy( 'bahia_cat', array( 'bahia' ), $args );
	}
	add_action( 'init', 'create_bahia_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_bahiaTag_taxonomies')) {
	function create_bahiaTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Bahia' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'bahia_tag', array( 'bahia' ), $args );
	}
	add_action( 'init', 'create_bahiaTag_taxonomies', 0 );
}