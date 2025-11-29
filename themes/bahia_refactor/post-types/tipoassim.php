<?php
/* BAHIA */

/* POST TYPE */
if (!function_exists('add_tipoassim')) {
	function add_tipoassim() 	{
		$labels = array(
			'name' => 'Tipo Assim',
			'menu_name' => 'Tipo Assim',
			'singular_name' => 'Tipo Assim'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'tipoassim', 'with_front' => false),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'tipoassim_cat' )
		); 
		register_post_type('tipoassim', $args);
	}
	add_action( 'init', 'add_tipoassim' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_tipoassim_taxonomies')) {
	function create_tipoassim_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Tipo Assim', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Tipo Assim' ),
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

		register_taxonomy( 'tipoassim_cat', array( 'tipoassim' ), $args );
	}
	add_action( 'init', 'create_tipoassim_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_tipoassimTag_taxonomies')) {
	function create_tipoassimTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Tipo Assim' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'tipoassim_tag', array( 'tipoassim' ), $args );
	}
	add_action( 'init', 'create_tipoassimTag_taxonomies', 0 );
}