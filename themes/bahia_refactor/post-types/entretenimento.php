<?php
/* ENTRETENIMENTO */

/* POST TYPE */
if (!function_exists('add_entretenimento')) {
	function add_entretenimento() 	{
		$labels = array(
			'name' => 'Entretenimento',
			'menu_name' => 'Entretenimento',
			'singular_name' => 'Entretenimento'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'entretenimento', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'entretenimento_cat' )
		); 
		register_post_type('entretenimento', $args);
	}
	add_action( 'init', 'add_entretenimento' );
}
/**/


/* TAXONOMY CATEGORIES */
if (!function_exists('create_entretenimento_taxonomies')) {
	function create_entretenimento_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Entretenimento', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Entretenimento' ),
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

		register_taxonomy( 'entretenimento_cat', array( 'entretenimento' ), $args );
	}
	add_action( 'init', 'create_entretenimento_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_entretenimentoTag_taxonomies')) {
	function create_entretenimentoTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Entretenimento' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'entretenimento_tag', array( 'entretenimento' ), $args );
	}
	add_action( 'init', 'create_entretenimentoTag_taxonomies', 0 );
}