<?php
/* BAHIA */

/* POST TYPE */
if (!function_exists('add_rms')) {
	function add_rms() 	{
		$labels = array(
			'name' => 'Mobilidade RMS',
			'menu_name' => 'Mobilidade RMS',
			'singular_name' => 'Mobilidade RMS'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'rms', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'rms_cat' )
		); 
		register_post_type('rms', $args);
	}
	add_action( 'init', 'add_rms' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_rms_taxonomies')) {
	function create_rms_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Mobilidade RMS', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Mobilidade RMS' ),
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

		register_taxonomy( 'rms_cat', array( 'rms' ), $args );
	}
	add_action( 'init', 'create_rms_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_rmsTag_taxonomies')) {
	function create_rmsTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Mobilidade RMS' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'rms_tag', array( 'rms' ), $args );
	}
	add_action( 'init', 'create_rmsTag_taxonomies', 0 );
}