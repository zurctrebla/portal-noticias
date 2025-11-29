<?php
/* BAHIA */

/* POST TYPE */
if (!function_exists('add_eleicoes2018')) {
	function add_eleicoes2018() 	{
		$labels = array(
			'name' => 'Eleições 2018',
			'menu_name' => 'Eleições 2018',
			'singular_name' => 'Eleições 2018'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'eleicoes2018', 'with_front' => false),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'eleicoes2018_cat' )
		); 
		register_post_type('eleicoes2018', $args);
	}
	add_action( 'init', 'add_eleicoes2018' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_eleicoes2018_taxonomies')) {
	function create_eleicoes2018_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Eleições 2018', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Eleições 2018' ),
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

		register_taxonomy( 'eleicoes2018_cat', array( 'eleicoes2018' ), $args );
	}
	add_action( 'init', 'create_eleicoes2018_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_eleicoes2018Tag_taxonomies')) {
	function create_eleicoes2018Tag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Eleições 2018' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'eleicoes2018_tag', array( 'eleicoes2018' ), $args );
	}
	add_action( 'init', 'create_eleicoes2018Tag_taxonomies', 0 );
}