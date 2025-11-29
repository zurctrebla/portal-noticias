<?php
/* ENTREVISTA */

/* POST TYPE */
if (!function_exists('add_entrevista')) {
	function add_entrevista() 	{
		$labels = array(
			'name' => 'Entrevistas',
			'menu_name' => 'Entrevistas',
			'singular_name' => 'Entrevistas'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'entrevista', 'with_front' => false),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'entrevista_cat' )
		); 
		register_post_type('entrevista', $args);
	}
	add_action( 'init', 'add_entrevista' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_entrevista_taxonomies')) {
	function create_entrevista_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Entrevistas', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Entrevistas' ),
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

		register_taxonomy( 'entrevista_cat', array( 'entrevista' ), $args );
	}
	add_action( 'init', 'create_entrevista_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_entrevistaTag_taxonomies')) {
	function create_entrevistaTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Entrevistas' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'entrevista_tag', array( 'entrevista' ), $args );
	}
	add_action( 'init', 'create_entrevistaTag_taxonomies', 0 );
}