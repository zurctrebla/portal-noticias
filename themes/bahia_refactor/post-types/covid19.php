<?php
/* BAHIA */

/* POST TYPE */
if (!function_exists('add_covid19')) {
	function add_covid19() 	{
		$labels = array(
			'name' => 'Covid-19',
			'menu_name' => 'Covid-19',
			'singular_name' => 'Covid-19'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'covid19', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'covid19_cat' )
		); 
		register_post_type('covid19', $args);
	}
	add_action( 'init', 'add_covid19' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_covid19_taxonomies')) {
	function create_covid19_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Covid-19', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Covid-19' ),
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

		register_taxonomy( 'covid19_cat', array( 'covid19' ), $args );
	}
	add_action( 'init', 'create_covid19_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_covid19Tag_taxonomies')) {
	function create_covid19Tag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Covid-19' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'covid19_tag', array( 'covid19' ), $args );
	}
	add_action( 'init', 'create_covid19Tag_taxonomies', 0 );
}