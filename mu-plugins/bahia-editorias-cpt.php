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
/**
 * OMITIR NÃO É APAGAR — as editorias com `show_in_menu => false`. Decidido em 18/08/2026.
 *
 * Nove editorias saíram do menu do painel a pedido da redação: Posts (o tipo NATIVO, tratado
 * no fim deste arquivo, não aqui), Bahia, Especial, Exclusivo, Mais Gente, Entrevistas,
 * Economia, Mais Notícias e Carnaval. Somam-se às duas que já vinham ocultas do tema antigo,
 * Gente e Bombou.
 *
 * O que NÃO muda, e é o ponto: `public`, `publicly_queryable`, `show_ui` e `has_archive`
 * continuam ligados. Os arquivos seguem no ar, as URLs seguem respondendo, o acervo fica
 * inteiro e as matérias continuam editáveis — o painel só para de OFERECER a editoria no
 * menu lateral e no "+ Novo". Quem precisar chega por `edit.php?post_type=<slug>`.
 *
 * As taxonomias {slug}_cat e {slug}_tag delas somem do menu junto, porque o core as pendura
 * como submenu do tipo. Elas continuam registradas e os termos continuam valendo.
 *
 * Para devolver uma ao menu, apague o `'show_in_menu' => false` da linha dela. É só isso —
 * não precisa de flush de rewrite (ver bahia_editorias_maybe_flush() no fim do arquivo:
 * `show_in_menu` não entra em regra de reescrita, e a versão do plugin NÃO foi bumpada de
 * propósito para não disparar um flush desnecessário).
 *
 * DUAS DESTAS AINDA PUBLICAVAM quando foram ocultadas, e isso está aqui para não virar
 * mistério daqui a seis meses: Bahia (420 matérias em 90 dias, última em 28/07/2026) e
 * Economia (53 em 90 dias, última em 24/07/2026). Não é editoria morta — é consolidação
 * editorial. As outras seis estavam paradas desde 2026-03 ou antes.
 */
function bahia_editorias_map() {
    return array(
        'politica'       => array('label' => 'Política',      'with_front' => true),
        'salvador'       => array('label' => 'Salvador',      'with_front' => true),
        'bahia'          => array('label' => 'Bahia',         'with_front' => true,  'show_in_menu' => false),
        'municipios'     => array('label' => 'Municípios',    'with_front' => true),
        'justica'        => array('label' => 'Justiça',       'with_front' => true),
        'especial'       => array('label' => 'Especial',      'with_front' => false, 'show_in_menu' => false),
        'exclusivo'      => array('label' => 'Exclusivo',     'with_front' => false, 'show_in_menu' => false),
        'esporte'        => array('label' => 'Esporte',       'with_front' => true),
        'brasil'         => array('label' => 'Brasil',        'with_front' => true),
        'entretenimento' => array('label' => 'Entretenimento','with_front' => true),
        'mais_gente'     => array('label' => 'Mais Gente',    'with_front' => false, 'show_in_menu' => false),
        'entrevista'     => array('label' => 'Entrevistas',   'with_front' => false, 'show_in_menu' => false),
        'economia'       => array('label' => 'Economia',      'with_front' => true,  'show_in_menu' => false),
        'mundo'          => array('label' => 'Mundo',         'with_front' => true),
        'mais_noticias'  => array('label' => 'Mais Notícias', 'with_front' => false, 'show_in_menu' => false),
        'artigo'         => array('label' => 'Artigos',       'with_front' => false),
        'carnaval'       => array('label' => 'Carnaval',      'with_front' => false, 'show_in_menu' => false),

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

/**
 * "Posts" — o tipo NATIVO do WordPress — também sai do menu (18/08/2026).
 *
 * Ele entrou no mesmo pedido das oito editorias acima, mas não está no mapa e nunca poderia
 * estar: `post` é registrado pelo próprio core, em create_initial_post_types(), muito antes
 * deste arquivo rodar. Por isso o mecanismo é outro — o filtro dos argumentos de registro.
 *
 * Conferido no core desta imagem antes de escrever, porque versões antigas do WordPress
 * fixavam o item na mão (`$menu[5] = array(__('Posts'), ...)`), e aí `show_in_menu` não
 * adiantaria nada. Nesta, wp-admin/menu.php:122-128 percorre
 * `array_merge(array('post','page'), $types)` e pula quem não tem `show_in_menu === true`.
 * Ou seja: o tipo nativo obedece ao mesmo argumento que as editorias — um mecanismo só para
 * as nove, em vez de um remove_menu_page() avulso.
 *
 * O "+ Novo > Post" da barra some junto: `show_in_admin_bar` cai no valor de `show_in_menu`
 * quando não é declarado, e o filtro roda ANTES desse padrão ser calculado (WP_Post_Type::
 * set_props aplica o filtro na primeira linha).
 *
 * Como nas editorias, isto é omissão e não remoção: `public` continua true, os posts nativos
 * seguem acessíveis e editáveis por `edit.php`. Some com ele o submenu de Categorias e Tags
 * nativas, que é filho deste tipo.
 */
add_filter('register_post_type_args', function ($args, $post_type) {
    if ('post' === $post_type) {
        $args['show_in_menu'] = false;
    }

    return $args;
}, 10, 2);
