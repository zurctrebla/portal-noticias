<?php
/* SERVIÇOS E CIDADANIA */

/* POST TYPE */
if (!function_exists('add_servicos')) {
	function add_servicos() 	{
		$labels = array(
			'name' => 'Serviços e Cidadania',
			'menu_name' => 'Serviços e Cidadania',
			'singular_name' => 'Serviços e Cidadania'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => false, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'servicos', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'servicos_cat' )
		); 
		register_post_type('servicos', $args);
	}
	add_action( 'init', 'add_servicos' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_servicos_taxonomies')) {
	function create_servicos_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Serviços e Cidadania', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Serviços e Cidadania' ),
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

		register_taxonomy( 'servicos_cat', array( 'servicos' ), $args );
	}
	add_action( 'init', 'create_servicos_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_servicosTag_taxonomies')) {
	function create_servicosTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Serviços e Cidadania' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'servicos_tag', array( 'servicos' ), $args );
	}
	add_action( 'init', 'create_servicosTag_taxonomies', 0 );
}