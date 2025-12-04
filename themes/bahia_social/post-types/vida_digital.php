<?php
/* VIDA DIGITAL */

/* POST TYPE */
if (!function_exists('add_digital')) {
	function add_digital() 	{
		$labels = array(
			'name' => 'Vida Digital',
			'menu_name' => 'Vida Digital',
			'singular_name' => 'Vida Digital'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => false, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'digital', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'digital_cat' )
		); 
		register_post_type('digital', $args);
	}
	add_action( 'init', 'add_digital' );
}
/**/



/* TAXONOMY CATEGORIES */
if (!function_exists('create_digital_taxonomies')) {
	function create_digital_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Vida Digital', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Vida Digital' ),
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

		register_taxonomy( 'digital_cat', array( 'digital' ), $args );
	}
	add_action( 'init', 'create_digital_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_digitalTag_taxonomies')) {
	function create_digitalTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Vida Digital' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'digital_tag', array( 'digital' ), $args );
	}
	add_action( 'init', 'create_digitalTag_taxonomies', 0 );
}