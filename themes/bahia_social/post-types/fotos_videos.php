<?php
/* FOTOS E VIDEOS */

/* POST TYPE */
if (!function_exists('add_fotos')) {
	function add_fotos() 	{
		$labels = array(
			'name' => 'Fotos e Vídeos',
			'menu_name' => 'Fotos e Vídeos',
			'singular_name' => 'Foto e Vídeo'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'fotos', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'fotos_cat' )
		); 
		register_post_type('fotos', $args);
	}
	add_action( 'init', 'add_fotos' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_fotos_taxonomies')) {
	function create_fotos_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Fotos e Vídeos', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Fotos e Vídeos' ),
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

		register_taxonomy( 'fotos_cat', array( 'fotos' ), $args );
	}
	add_action( 'init', 'create_fotos_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_fotosTag_taxonomies')) {
	function create_fotosTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Fotos e Vídeos' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'fotos_tag', array( 'fotos' ), $args );
	}
	add_action( 'init', 'create_fotosTag_taxonomies', 0 );
}