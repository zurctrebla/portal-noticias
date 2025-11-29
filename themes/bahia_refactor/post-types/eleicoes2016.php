<?php
/* ELEIÇÕES */

/* POST TYPE */
if (!function_exists('add_eleicoes2016')) {
	function add_eleicoes2016() 	{
		$labels = array(
			'name' => 'Eleições 2016',
			'menu_name' => 'Eleições 2016',
			'singular_name' => 'Eleições 2016'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'eleicoes2016', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'eleicoes2016_cat' )
		); 
		register_post_type('eleicoes2016', $args);
	}
	add_action( 'init', 'add_eleicoes2016' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_eleicoes2016_taxonomies')) {
	function create_eleicoes2016_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Eleições 2016', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Eleições 2016' ),
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

		register_taxonomy( 'eleicoes2016_cat', array( 'eleicoes2016' ), $args );
	}
	add_action( 'init', 'create_eleicoes2016_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_eleicoes2016Tag_taxonomies')) {
	function create_eleicoes2016Tag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Eleições 2016' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'eleicoes2016_tag', array( 'eleicoes2016' ), $args );
	}
	add_action( 'init', 'create_eleicoes2016Tag_taxonomies', 0 );
}