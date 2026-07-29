<?php
/**
 * Plugin Name: Bahia.ba - Editorias (CPTs)
 * Description: Registra os Custom Post Types das editorias (e taxonomias *_cat / *_tag)
 *              de forma independente do tema. Necessário após a migração do tema
 *              bahia_refactor -> Newspaper: os CPTs eram registrados pelo tema antigo
 *              e deixaram de existir sob o Newspaper, quebrando os archives (/politica,
 *              /esporte, ...) e o menu. Args portados fielmente de
 *              wp-content/themes/bahia_refactor/post-types/*.php
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

// Bump esta versão sempre que alterar rewrite/slug/args para forçar novo flush.
define('BAHIA_EDITORIAS_CPT_VER', '1.0.0');

/**
 * Editorias: slug (== nome do CPT == rewrite slug) => [label, with_front].
 * 'with_front' reproduz exatamente o que cada arquivo original definia
 * (false onde o tema usava 'with_front' => false; true = default do WP).
 */
function bahia_editorias_map() {
    return array(
        'politica'       => array('label' => 'Política',      'with_front' => true),
        'salvador'       => array('label' => 'Salvador',      'with_front' => true),
        'bahia'          => array('label' => 'Bahia',         'with_front' => true),
        'municipios'     => array('label' => 'Municípios',    'with_front' => true),
        'justica'        => array('label' => 'Justiça',       'with_front' => true),
        'especial'       => array('label' => 'Especial',      'with_front' => false),
        'exclusivo'      => array('label' => 'Exclusivo',     'with_front' => false),
        'esporte'        => array('label' => 'Esporte',       'with_front' => true),
        'brasil'         => array('label' => 'Brasil',        'with_front' => true),
        'entretenimento' => array('label' => 'Entretenimento','with_front' => true),
        'mais_gente'     => array('label' => 'Mais Gente',    'with_front' => false),
        'entrevista'     => array('label' => 'Entrevistas',   'with_front' => false),
        'economia'       => array('label' => 'Economia',      'with_front' => true),
        'mundo'          => array('label' => 'Mundo',         'with_front' => true),
        'mais_noticias'  => array('label' => 'Mais Notícias', 'with_front' => false),
        'artigo'         => array('label' => 'Artigos',       'with_front' => false),
        'carnaval'       => array('label' => 'Carnaval',      'with_front' => false),
    );
}

/**
 * Registra CPTs + taxonomias (categoria e tag) de cada editoria.
 * Fiel ao tema antigo: has_archive=true, rewrite slug = editoria,
 * taxonomia hierárquica {slug}_cat (rewrite 'categoria') e {slug}_tag (rewrite 'tag').
 */
function bahia_editorias_register() {
    foreach (bahia_editorias_map() as $slug => $ed) {
        $label = $ed['label'];

        // --- Custom Post Type ---
        register_post_type($slug, array(
            'labels' => array(
                'name'          => $label,
                'menu_name'     => $label,
                'singular_name' => $label,
            ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array('slug' => $slug, 'with_front' => $ed['with_front']),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 5,
            'supports'           => array('title', 'editor', 'author', 'comments'),
            'taxonomies'         => array($slug . '_cat'),
        ));

        // --- Taxonomia de categorias ({slug}_cat) ---
        register_taxonomy($slug . '_cat', array($slug), array(
            'hierarchical'      => true,
            'labels'            => array(
                'name'          => 'Categorias de ' . $label,
                'singular_name' => 'Categoria',
                'menu_name'     => 'Categorias de ' . $label,
            ),
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'categoria'),
        ));

        // --- Taxonomia de tags ({slug}_tag) ---
        register_taxonomy($slug . '_tag', array($slug), array(
            'hierarchical'      => false,
            'labels'            => array(
                'name'          => 'Tags de ' . $label,
                'singular_name' => 'Tag',
                'menu_name'     => 'Tags de ' . $label,
            ),
            'show_ui'           => true,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array('slug' => 'tag'),
        ));
    }
}
add_action('init', 'bahia_editorias_register', 0);

/**
 * mu-plugins não têm hook de ativação; fazemos o flush uma única vez por versão,
 * após os CPTs já estarem registrados (prioridade alta no init).
 */
function bahia_editorias_maybe_flush() {
    if (get_option('bahia_editorias_cpt_flushed') !== BAHIA_EDITORIAS_CPT_VER) {
        flush_rewrite_rules(false);
        update_option('bahia_editorias_cpt_flushed', BAHIA_EDITORIAS_CPT_VER);
    }
}
add_action('init', 'bahia_editorias_maybe_flush', 999);
