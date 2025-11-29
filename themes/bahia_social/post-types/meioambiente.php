<?php
/* MEIOAMBIENTE */

/* POST TYPE */
if (!function_exists('add_meioambiente')) {
	function add_meioambiente() 	{
		$labels = array(
			'name' => 'Meio Ambiente',
			'menu_name' => 'Meio Ambiente',
			'singular_name' => 'Meio Ambiente'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => false, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'meio-ambiente', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'meioambiente_cat' )
		); 
		register_post_type('meioambiente', $args);
	}
	add_action( 'init', 'add_meioambiente' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_meioambiente_taxonomies')) {
	function create_meioambiente_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Meio ambiente', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Meio ambiente' ),
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

		register_taxonomy( 'meioambiente_cat', array( 'meioambiente' ), $args );
	}
	add_action( 'init', 'create_meioambiente_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_meioambienteTag_taxonomies')) {
	function create_meioambienteTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Meio ambiente' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'meioambiente_tag', array( 'meioambiente' ), $args );
	}
	add_action( 'init', 'create_meioambienteTag_taxonomies', 0 );
}