<?php
/* CARNAVAL */

/* POST TYPE */
if (!function_exists('add_carnaval')) {
	function add_carnaval() 	{
		$labels = array(
			'name' => 'Carnaval',
			'menu_name' => 'Carnaval',
			'singular_name' => 'Carnaval'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'carnaval', 'with_front' => false),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'carnaval_cat' )
		); 
		register_post_type('carnaval', $args);
	}
	add_action( 'init', 'add_carnaval' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_carnaval_taxonomies')) {
	function create_carnaval_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Carnaval', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Carnaval' ),
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

		register_taxonomy( 'carnaval_cat', array( 'carnaval' ), $args );
	}
	add_action( 'init', 'create_carnaval_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_carnavalTag_taxonomies')) {
	function create_carnavalTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Carnaval' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'carnaval_tag', array( 'carnaval' ), $args );
	}
	add_action( 'init', 'create_carnavalTag_taxonomies', 0 );
}