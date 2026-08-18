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
define('BAHIA_EDITORIAS_CPT_VER', '1.2.0');

/**
 * Editorias: slug (== nome do CPT == taxonomia {slug}_cat/_tag) => argumentos.
 *
 *   'label'        labels['name'] — NÃO é só rótulo de painel: é o que
 *                  post_type_archive_title() imprime no topo do archive.
 *   'menu_name'    opcional; default = label. Usado no menu do painel e nos
 *                  rótulos das taxonomias.
 *   'singular'     opcional; default = label.
 *   'with_front'   reproduz exatamente o que cada arquivo original definia
 *                  (false onde o tema usava 'with_front' => false).
 *   'rewrite'      opcional; slug de URL diferente do nome interno do CPT, para
 *                  editorias cujo nome interno precisa ser identificador válido
 *                  de post_type mas a URL deve ter hífens (dende_poder ->
 *                  /dende-e-poder).
 *   'show_in_menu' opcional; default true.
 *
 * ATENÇÃO: esta lista é a fonte da verdade de quais editorias existem depois da
 * troca de tema. O bahia_refactor mantinha a mesma lista à mão em
 * functions.php:56 e ela apodreceu: 46 arquivos em post-types/, 24 na lista.
 * As editorias que saírem daqui deixam de existir, e o acervo delas vai a 404.
 * Ela também alimenta bahia_ft_types() do bahia-fulltext-search.php — um tipo
 * ausente aqui é apagado da tabela-sombra de busca a cada save_post.
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

        // --- Editorias que o bahia_refactor registrava e este mapa não cobria ---
        // Levantadas em 18/08/2026 comparando $wp_post_types em tempo de execução
        // nos dois ambientes: 15.540 matérias publicadas iriam a 404 sem elas.
        // Argumentos copiados de themes/bahia_refactor/post-types/*.php, com os
        // quatro casos que uma cópia por padrão erraria marcados abaixo.
        'covid19'        => array('label' => 'Covid-19',      'with_front' => true),
        // with_front FALSE (post-types/eleicoes2024.php:19)
        'eleicoes2024'   => array('label' => 'Eleições 2024', 'with_front' => false),
        'saude'          => array('label' => 'Saúde e Bem Estar', 'with_front' => true),
        // labels['name'] é 'Coluna do Ginno' — é o título do archive /social/.
        // O menu do painel e as taxonomias usam 'Social' (post-types/social.php:7-11).
        'social'         => array('label' => 'Coluna do Ginno', 'menu_name' => 'Social',
                                  'singular' => 'Social', 'with_front' => true),
        // show_in_menu FALSE (post-types/gente.php:17)
        'gente'          => array('label' => 'Gente',         'with_front' => true,
                                  'show_in_menu' => false),
        'investimentos'  => array('label' => 'Investimentos', 'with_front' => true),
        // show_in_menu FALSE e with_front FALSE (post-types/bombou.php:17,19)
        'bombou'         => array('label' => 'Bombou',        'with_front' => false,
                                  'show_in_menu' => false),

        // Editoria nova (não existia no tema antigo): nome interno com underscore,
        // URL com hífens (/dende-e-poder), taxonomia dende_poder_cat / dende_poder_tag.
        'dende_poder'    => array('label' => 'Dendê e Poder', 'with_front' => true, 'rewrite' => 'dende-e-poder'),
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
        // menu_name/singular caem no label quando não declarados — é o caso de
        // todas as editorias menos 'social', cujo nome de archive ('Coluna do
        // Ginno') difere do nome no painel ('Social').
        $menu_name    = isset($ed['menu_name']) ? $ed['menu_name'] : $label;
        $singular     = isset($ed['singular'])  ? $ed['singular']  : $label;
        $show_in_menu = isset($ed['show_in_menu']) ? $ed['show_in_menu'] : true;
        // Slug de URL: por padrão == nome interno; editorias novas podem
        // definir um 'rewrite' diferente (ex: dende_poder -> dende-e-poder).
        $rewrite_slug = isset($ed['rewrite']) ? $ed['rewrite'] : $slug;

        // --- Custom Post Type ---
        register_post_type($slug, array(
            'labels' => array(
                'name'          => $label,
                'menu_name'     => $menu_name,
                'singular_name' => $singular,
            ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => $show_in_menu,
            'query_var'          => true,
            'rewrite'            => array('slug' => $rewrite_slug, 'with_front' => $ed['with_front']),
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
                'name'          => 'Categorias de ' . $menu_name,
                'singular_name' => 'Categoria',
                'menu_name'     => 'Categorias de ' . $menu_name,
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
                'name'          => 'Tags de ' . $menu_name,
                'singular_name' => 'Tag',
                'menu_name'     => 'Tags de ' . $menu_name,
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
