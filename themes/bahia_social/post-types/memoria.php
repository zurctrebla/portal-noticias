<?php
/* MEMORIA */

/* POST TYPE */
if (!function_exists('add_memoria')) {
	function add_memoria() 	{
		$labels = array(
			'name' => 'Memória',
			'menu_name' => 'Memória',
			'singular_name' => 'Memória'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'memoria', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'memoria_cat' )
		); 
		register_post_type('memoria', $args);
	}
	add_action( 'init', 'add_memoria' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_memoria_taxonomies')) {
	function create_memoria_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Memória', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Memória' ),
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

		register_taxonomy( 'memoria_cat', array( 'memoria' ), $args );
	}
	add_action( 'init', 'create_memoria_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_memoriaTag_taxonomies')) {
	function create_memoriaTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Memória' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'memoria_tag', array( 'memoria' ), $args );
	}
	add_action( 'init', 'create_memoriaTag_taxonomies', 0 );
}