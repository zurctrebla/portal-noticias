<?php
/* BAHIA */

/* POST TYPE */
if (!function_exists('add_carnaval2019')) {
	function add_carnaval2019() 	{
		$labels = array(
			'name' => 'Carnaval 2019',
			'menu_name' => 'Carnaval 2019',
			'singular_name' => 'Carnaval 2019'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'carnaval2019', 'with_front' => false),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'carnaval2019_cat' )
		); 
		register_post_type('carnaval2019', $args);
	}
	add_action( 'init', 'add_carnaval2019' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_carnaval2019_taxonomies')) {
	function create_carnaval2019_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Carnaval 2019', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Carnaval 2019' ),
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

		register_taxonomy( 'carnaval2019_cat', array( 'carnaval2019' ), $args );
	}
	add_action( 'init', 'create_carnaval2019_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_carnaval2019Tag_taxonomies')) {
	function create_carnaval2019Tag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Carnaval 2019' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'carnaval2019_tag', array( 'carnaval2019' ), $args );
	}
	add_action( 'init', 'create_carnaval2019Tag_taxonomies', 0 );
}