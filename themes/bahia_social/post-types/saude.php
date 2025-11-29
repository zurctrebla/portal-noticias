<?php
/* SAUDE E BEM ESTAR */

/* POST TYPE */
if (!function_exists('add_saude')) {
	function add_saude() 	{
		$labels = array(
			'name' => 'Saúde e Bem Estar',
			'menu_name' => 'Saúde e Bem Estar',
			'singular_name' => 'Saúde e Bem Estar'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'saude', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'saude_cat' )
		); 
		register_post_type('saude', $args);
	}
	add_action( 'init', 'add_saude' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_saude_taxonomies')) {
	function create_saude_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Saúde e Bem Estar', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Saúde e Bem Estar' ),
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

		register_taxonomy( 'saude_cat', array( 'saude' ), $args );
	}
	add_action( 'init', 'create_saude_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_saudeTag_taxonomies')) {
	function create_saudeTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Saúde e Bem Estar' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'saude_tag', array( 'saude' ), $args );
	}
	add_action( 'init', 'create_saudeTag_taxonomies', 0 );
}