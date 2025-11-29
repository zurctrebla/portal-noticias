<?php
/* EMPREGOS E CONCURSOS */

/* POST TYPE */
if (!function_exists('add_emprego')) {
	function add_emprego() 	{
		$labels = array(
			'name' => 'Empregos e Concursos',
			'menu_name' => 'Empregos e Concursos',
			'singular_name' => 'Empregos e Concursos'
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true, 
			'show_in_menu' => false, 
			'query_var' => true,
			'rewrite' => array( 'slug' => _x( 'emprego', 'URL slug', 'your_text_domain' ) ),
			'capability_type' => 'post',
			'has_archive' => true, 
			'hierarchical' => false,
			'menu_position' => 5,
			'supports' => array( 'title','editor', 'author','comments' ),
			'taxonomies' => array( 'emprego_cat' )
		); 
		register_post_type('emprego', $args);
	}
	add_action( 'init', 'add_emprego' );
}
/**/

/* TAXONOMY CATEGORIES */
if (!function_exists('create_emprego_taxonomies')) {
	function create_emprego_taxonomies() {

		$labels = array(
			'name'              => _x( 'Categorias de Empregos e Concursos', 'taxonomy general name' ),
			'singular_name'     => _x( 'Categoria', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Categorias' ),
			'all_items'         => __( 'Todas as Categorias' ),
			'edit_item'         => __( 'Editar Categoria' ),
			'add_new_item'      => __( 'Adicionar Categoria' ),
			'new_item_name'     => __( 'Nova Categoria' ),
			'menu_name'         => __( 'Categorias de Empregos e Concursos' ),
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

		register_taxonomy( 'emprego_cat', array( 'emprego' ), $args );
	}
	add_action( 'init', 'create_emprego_taxonomies', 0 );
}
/**/

/* TAXONOMY TAGS */
if (!function_exists('create_empregoTag_taxonomies')) {
	function create_empregoTag_taxonomies() {

		$labels = array(
			'name'              => _x( 'Tags', 'taxonomy general name' ),
			'singular_name'     => _x( 'Tag', 'taxonomy singular name' ),
			'search_items'      => __( 'Buscar Tags' ),
			'all_items'         => __( 'Todas as Tags' ),
			'edit_item'         => __( 'Editar Tag' ),
			'add_new_item'      => __( 'Adicionar Tag' ),
			'new_item_name'     => __( 'Nova Tag' ),
			'menu_name'         => __( 'Tags de Empregos e Concursos' ),
		);

		$args = array(
			'hierarchical'      => false,
			'labels'            => $labels,
			'show_ui'           => true,
			'show_admin_column' => true,
			'query_var'         => true,
			'rewrite'           => array( 'slug' => 'tag' ),
		);

		register_taxonomy( 'emprego_tag', array( 'emprego' ), $args );
	}
	add_action( 'init', 'create_empregoTag_taxonomies', 0 );
}