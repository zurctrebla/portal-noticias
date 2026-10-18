<?php
/**
 * Plugin Name: Bahia.ba - SEO das matérias (data, autoria e editoria)
 * Description: KAN-22. A Marfeel apontou que o Google não reconhece, nas matérias, a data
 *              de publicação, a autoria nem a editoria. Medido em 18/10/2026 numa matéria
 *              de /politica/ em produção: o JSON-LD do Yoast trazia só WebPage, sem nó
 *              Article (logo sem author nem articleSection), e faltavam as meta tags
 *              article:published_time, author e article:section.
 *
 *              A CAUSA: as matérias não são `post`. Cada editoria é um CPT próprio
 *              (bahia-editorias-cpt.php), e o Yoast trata `post` à parte:
 *
 *                1. JSON-LD — o nó Article só sai se `schema-article-type-{tipo}` em
 *                   wpseo_titles for diferente de 'None'. Em `post` é 'Article'; em
 *                   TODAS as editorias estava 'None' (padrão do Yoast para CPT).
 *                   Com 'None' o filtro `wpseo_schema_article_type` nem é chamado
 *                   (context/meta-tags-context.php), então a troca tem de ser na
 *                   leitura da opção.
 *                2. article:published_time — só para `post`, salvo o filtro
 *                   `wpseo_opengraph_show_publish_date`.
 *                3. <meta name="author"> — Meta_Author_Presenter devolve '' para
 *                   qualquer tipo que não seja `post`, sem filtro. Sai daqui.
 *                4. article:section — o Yoast não emite. Sai daqui.
 *
 *              Tudo por código e não pela tela do Yoast: vale igual nos dois ambientes,
 *              fica versionado, e uma editoria nova entra sozinha pelo mapa de CPTs.
 *
 *              Editoria = rótulo do CPT (o mesmo do menu e do breadcrumb). A autoria
 *              respeita o Co-Authors Plus: no JSON-LD a integração dele com o Yoast já
 *              lista cada coautor; na meta tag vão todos os nomes, "A e B".
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Tipo de artigo no schema das editorias. NewsArticle é o que o Google News lê. */
const BAHIA_SEO_ARTICLE_TYPE = 'NewsArticle';

/** O tipo de post é uma editoria? */
function bahia_seo_eh_editoria($post_type) {
    return is_string($post_type)
        && function_exists('bahia_editorias_map')
        && array_key_exists($post_type, bahia_editorias_map());
}

/** Post da matéria sendo exibida, ou null fora de uma matéria de editoria. */
function bahia_seo_materia() {
    if (is_admin() || !is_singular()) {
        return null;
    }
    $post = get_queried_object();
    if (!$post instanceof WP_Post || !bahia_seo_eh_editoria($post->post_type)) {
        return null;
    }
    return $post;
}

/** Nome da editoria (rótulo do CPT). */
function bahia_seo_editoria_nome($post_type) {
    $obj = get_post_type_object($post_type);
    return $obj ? $obj->labels->name : '';
}

/**
 * 1. JSON-LD: troca o 'None' das editorias por NewsArticle na LEITURA de wpseo_titles.
 *
 * Só onde está 'None' (ou ausente): se alguém escolher outro tipo na tela do Yoast, a
 * escolha vale. No front e no painel por igual, para a tela mostrar o que o site emite.
 */
add_filter('option_wpseo_titles', function ($titles) {
    if (!is_array($titles) || !function_exists('bahia_editorias_map')) {
        return $titles;
    }
    foreach (array_keys(bahia_editorias_map()) as $pt) {
        $k = 'schema-article-type-' . $pt;
        if (empty($titles[$k]) || $titles[$k] === 'None') {
            $titles[$k] = BAHIA_SEO_ARTICLE_TYPE;
        }
    }
    return $titles;
});

/**
 * articleSection = editoria + categorias internas ({tipo}_cat), sem repetir.
 * O Yoast só olha a taxonomia `category`, que as editorias não usam.
 */
add_filter('wpseo_schema_article', function ($data) {
    $post = bahia_seo_materia();
    if (!$post) {
        return $data;
    }
    $secoes = array(bahia_seo_editoria_nome($post->post_type));
    $terms  = get_the_terms($post, $post->post_type . '_cat');
    if (is_array($terms)) {
        foreach ($terms as $t) {
            $secoes[] = $t->name;
        }
    }
    $data['articleSection'] = array_values(array_unique(array_filter($secoes)));
    return $data;
});

/** keywords a partir das tags da editoria ({tipo}_tag), no lugar de post_tag. */
add_filter('wpseo_schema_article_keywords_taxonomy', function ($taxonomy) {
    $post = bahia_seo_materia();
    return $post ? $post->post_type . '_tag' : $taxonomy;
});

/** 2. article:published_time também nas editorias. */
add_filter('wpseo_opengraph_show_publish_date', function ($show, $post_type) {
    return $show || bahia_seo_eh_editoria($post_type);
}, 10, 2);

/**
 * 3 e 4. <meta name="author"> e article:section, dentro do bloco do Yoast.
 *
 * `wpseo_head` roda entre os comentários "This site is optimized with..." e
 * "/ Yoast SEO plugin.", junto das outras meta tags da página.
 */
add_action('wpseo_head', function () {
    $post = bahia_seo_materia();
    if (!$post) {
        return;
    }

    $nomes = function_exists('bahia_get_coauthor_names') ? bahia_get_coauthor_names($post->ID) : array();
    if (!$nomes) {
        $nomes = array(get_the_author_meta('display_name', $post->post_author));
    }
    $nomes  = array_filter($nomes);
    $autor  = function_exists('bahia_join_author_names') ? bahia_join_author_names($nomes) : implode(', ', $nomes);
    $secao  = bahia_seo_editoria_nome($post->post_type);

    if ($autor !== '') {
        printf("\t<meta name=\"author\" content=\"%s\" />\n", esc_attr($autor));
    }
    if ($secao !== '') {
        printf("\t<meta property=\"article:section\" content=\"%s\" />\n", esc_attr($secao));
    }
}, 50);
