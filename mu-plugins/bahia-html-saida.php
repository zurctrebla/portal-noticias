<?php
/**
 * Plugin Name: Bahia.ba - Reescritas dirigidas no HTML de saída
 * Description: Correções que só podem ser feitas no HTML já montado, porque a origem está
 *              cravada dentro de plugins/ (que não editamos) e não passa por nenhum filtro
 *              do WordPress.
 *
 *              1) BYLINE DE COAUTORIA — td_module_single_base::get_author() (td-composer,
 *                 linha 84) monta UM <a> só, envolvendo get_the_author_meta('display_name').
 *                 Com o bahia-coautores.php trocando esse display_name por "A e B", os dois
 *                 nomes acabam dentro do mesmo link, apontando para o primeiro autor —
 *                 clicar em "Neison Cerqueira" levava ao perfil do Daniel Serrano.
 *                 Aqui o <a> único é trocado por um link por autor, com o " e " fora deles.
 *
 *                 Por que no HTML de saída e não por filtro: get_author() não expõe hook
 *                 algum, e mexer em get_the_author_display_name para devolver marcação
 *                 vazaria HTML para outros consumidores do mesmo dado no singular — o alt
 *                 do avatar (get_author_photo) e as meta tags do Yoast (og:article:author,
 *                 JSON-LD). A reescrita dirigida no byline não toca nesses.
 *
 *              2) ARIA-LABELS EM INGLÊS — aria-label="Search" está escrito à mão em
 *                 td-composer/legacy/Newspaper/header.php:139/141, sem passar por __td().
 *                 (O "Load more" segue por outro caminho: passa por __td() e é traduzido
 *                 pelo td_translation_map_user em bahia-traducoes.php.)
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Abre o buffer de saída. Prioridade 1 para envolver o template inteiro.
 * O PHP dispara o callback sozinho ao final da requisição.
 */
function bahia_hs_start() {
    if (is_admin() || is_feed() || is_robots()) {
        return;
    }
    if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
        return;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return;
    }
    if (defined('DOING_CRON') && DOING_CRON) {
        return;
    }
    // Sitemaps do Yoast e afins: saída XML, não mexer.
    if (get_query_var('sitemap') || get_query_var('sitemap_n')) {
        return;
    }
    ob_start('bahia_hs_filtrar');
}
add_action('template_redirect', 'bahia_hs_start', 1);

/**
 * Callback do buffer: só age em documentos HTML completos.
 */
function bahia_hs_filtrar($html) {
    if (!is_string($html) || $html === '') {
        return $html;
    }
    // Guarda contra XML/JSON/parciais que porventura passem por aqui.
    if (stripos($html, '<html') === false) {
        return $html;
    }

    $html = bahia_hs_aria_labels($html);
    $html = bahia_hs_byline_coautores($html);

    /**
     * Ponto de extensão para outras reescritas de HTML servido, para que só exista
     * UM buffer de saída no site. Ex.: o selo EXCLUSIVO (bahia-exclusivo.php).
     */
    return apply_filters('bahia_hs_html', $html);
}

/**
 * aria-labels cravados no HTML dos plugins.
 */
function bahia_hs_aria_labels($html) {
    // As duas primeiras são as do briefing; as de navegação de slider vinham como
    // slug cru ("next-page"), que o leitor de tela soletra.
    return str_replace(
        array(
            'aria-label="Search"',
            'aria-label="Close"',
            'aria-label="next-page"',
            'aria-label="prev-page"',
            'aria-label="next"',
            'aria-label="prev"',
        ),
        array(
            'aria-label="Buscar"',
            'aria-label="Fechar"',
            'aria-label="Próxima página"',
            'aria-label="Página anterior"',
            'aria-label="Próximo"',
            'aria-label="Anterior"',
        ),
        $html
    );
}

/**
 * Troca o <a> único do byline por um link para cada coautor.
 *
 * Escopo: só o post consultado, só quando há 2+ coautores, e só dentro do bloco
 * .td-post-author-name (não toca na caixa de autor nem em cards de relacionados).
 */
function bahia_hs_byline_coautores($html) {
    if (!is_singular() || !function_exists('get_coauthors')) {
        return $html;
    }
    if (!function_exists('bahia_join_author_names')) {
        return $html; // bahia-coautores.php ausente: nada a corrigir
    }

    $post_id = (int) get_queried_object_id();
    if (!$post_id) {
        return $html;
    }

    $names = array();
    $links = array();
    foreach (get_coauthors($post_id) as $ca) {
        if (!is_object($ca) || empty($ca->display_name)) {
            continue;
        }
        $url = get_author_posts_url(
            isset($ca->ID) ? $ca->ID : 0,
            isset($ca->user_nicename) ? $ca->user_nicename : ''
        );
        $names[] = $ca->display_name;
        $links[] = '<a href="' . esc_url($url) . '">' . esc_html($ca->display_name) . '</a>';
    }

    if (count($names) < 2) {
        return $html; // autor único: o markup do tema já está correto
    }

    // O texto do <a> atual é exatamente o que o bahia-coautores.php devolveu.
    $texto_atual = bahia_join_author_names($names);
    // Mesma junção, mas com os separadores FORA dos links.
    $substituto  = bahia_join_author_names($links);

    // .{0,200}? cobre o "<div class="td-author-by">Por</div> " que vem antes do link,
    // sem deixar o .*? varrer a página inteira caso o markup mude.
    $pattern = '#(<div class="td-post-author-name[^"]*">.{0,200}?)<a\s+href="[^"]*">'
             . preg_quote($texto_atual, '#')
             . '</a>#s';

    // Callback (e não string de substituição) para que "$" e "\" em nomes de autor
    // não sejam interpretados como retrovisor.
    $novo = preg_replace_callback(
        $pattern,
        function ($m) use ($substituto) {
            return $m[1] . $substituto;
        },
        $html,
        1
    );

    // preg_replace_callback devolve null em erro de PCRE (ex.: backtrack limit).
    return is_string($novo) ? $novo : $html;
}
