<?php
/* BAHIA */

/* POST TYPE */
if (!function_exists('add_mais_gente')) {
	function add_mais_gente() 	{
		$labels = array(
			'name' => 'Mais Gente',
			'menu_name' => 'Mais Gente',
			'singular_name' => 'Mais Gente'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => true, 
			'query_var' => true,
			'rewrite' => array( 'slug' => 'mais_gente', 'with_front' => false),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'mais_gente_cat' )
		); 
		register_post_type('mais_gente', $args);
	}
	add_action( 'init', 'add_mais_gente' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_mais_gente_taxonomies')) {
	function create_mais_gente_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Mais Gente', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Mais Gente' ),
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

		register_taxonomy( 'mais_gente_cat', array( 'mais_gente' ), $args );
	}
	add_action( 'init', 'create_mais_gente_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_mais_genteTag_taxonomies')) {
	function create_mais_genteTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Mais Gente' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'mais_gente_tag', array( 'mais_gente' ), $args );
	}
	add_action( 'init', 'create_mais_genteTag_taxonomies', 0 );
}