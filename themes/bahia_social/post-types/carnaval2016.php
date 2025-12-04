<?php
/* BAHIA */

/* POST TYPE */
if (!function_exists('add_carnaval2016')) {
	function add_carnaval2016() 	{
		$labels = array(
			'name' => 'Carnaval 2016',
			'menu_name' => 'Carnaval 2016',
			'singular_name' => 'Carnaval 2016'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'carnaval2016', 'with_front' => false),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'carnaval2016_cat' )
		); 
		register_post_type('carnaval2016', $args);
	}
	add_action( 'init', 'add_carnaval2016' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_carnaval2016_taxonomies')) {
	function create_carnaval2016_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Carnaval 2016', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Carnaval 2016' ),
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

		register_taxonomy( 'carnaval2016_cat', array( 'carnaval2016' ), $args );
	}
	add_action( 'init', 'create_carnaval2016_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_carnaval2016Tag_taxonomies')) {
	function create_carnaval2016Tag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Carnaval 2016' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'carnaval2016_tag', array( 'carnaval2016' ), $args );
	}
	add_action( 'init', 'create_carnaval2016Tag_taxonomies', 0 );
}