<?php
/**
 * Plugin Name: Bahia.ba - Limites de texto nos listings
 * Description: Corta títulos em 70 e resumos em 160 caracteres nos listings (home,
 *              arquivos de editoria, busca, sidebar e blocos relacionados), sempre em
 *              palavra inteira. NÃO toca no <h1> do single, no <title>, nas meta tags
 *              do Yoast, nos feeds, no admin nem nos itens de menu.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_LIMITE_TITULO', 70);
define('BAHIA_LIMITE_RESUMO', 160);

/**
 * Corta em palavra inteira e devolve o texto pronto para sair em HTML.
 *
 * Mede sobre o texto limpo (sem tags, com entidades decodificadas) porque é isso que
 * o leitor conta como caractere: "Baianão" tem 7, não 12 de "Baian&atilde;o".
 * Reescreve as entidades na saída — o texto cortado volta para dentro do HTML.
 *
 * @return string|null null quando já cabe no limite (aí o chamador devolve o original
 *                     intacto, sem reescrever entidade nenhuma à toa).
 */
function bahia_limites_cortar($texto, $limite) {
    if (!is_string($texto) || $texto === '') {
        return null;
    }

    $limpo = html_entity_decode(wp_strip_all_tags($texto), ENT_QUOTES, 'UTF-8');
    $limpo = trim(preg_replace('/\s+/u', ' ', $limpo));

    if ($limpo === '' || mb_strlen($limpo, 'UTF-8') <= $limite) {
        return null;
    }

    $corte = mb_substr($limpo, 0, $limite, 'UTF-8');

    // Recua até o último espaço para não partir palavra. O piso de metade do limite
    // evita o caso patológico de uma "palavra" gigante (URL colada) zerar o texto.
    $espaco = mb_strrpos($corte, ' ', 0, 'UTF-8');
    if ($espaco !== false && $espaco >= (int) ($limite / 2)) {
        $corte = mb_substr($corte, 0, $espaco, 'UTF-8');
    }

    $corte = rtrim($corte, " \t\n\r\0\x0B.,;:!?-–—…");

    if ($corte === '') {
        return null;
    }

    return htmlspecialchars($corte . '…', ENT_QUOTES, 'UTF-8', false);
}

/**
 * Contextos onde nada pode ser cortado.
 *
 * wp_doing_ajax() é a exceção dentro de is_admin(): os cards do "Ver mais" e do scroll
 * infinito são servidos por admin-ajax.php, onde is_admin() é true. Excluir o admin em
 * bloco deixaria justamente os cards carregados sob demanda com título inteiro,
 * destoando dos que vieram no HTML inicial.
 */
function bahia_limites_contexto_isento() {
    if (is_admin() && !wp_doing_ajax()) {
        return true;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return true;
    }
    if (defined('DOING_CRON') && DOING_CRON) {
        return true;
    }
    if (is_feed() || is_embed()) {
        return true;
    }
    // O <title> e as meta tags montam-se dentro destes filtros.
    foreach (array('wp_nav_menu', 'document_title_parts', 'wp_title', 'pre_get_document_title',
                   'wpseo_title', 'wpseo_metadesc', 'wpseo_opengraph_title',
                   'wpseo_opengraph_desc', 'wpseo_twitter_title', 'wpseo_twitter_description') as $f) {
        if (doing_filter($f)) {
            return true;
        }
    }
    return false;
}

/**
 * O post que dá nome à página nunca é cortado: é o <h1> do single.
 * Comparar pelo objeto consultado, e não por in_the_loop(), porque o single do portal
 * renderiza pelo loop-single.php do td-composer, que não é um loop padrão do WP.
 */
function bahia_limites_e_o_post_da_pagina($post_id) {
    return is_singular() && $post_id && (int) $post_id === (int) get_queried_object_id();
}

function bahia_limites_titulo($title, $post_id = null) {
    if (!is_string($title) || $title === '' || bahia_limites_contexto_isento()) {
        return $title;
    }
    if ($post_id && get_post_type($post_id) === 'nav_menu_item') {
        return $title;
    }
    if (bahia_limites_e_o_post_da_pagina($post_id)) {
        return $title;
    }

    $corte = bahia_limites_cortar($title, BAHIA_LIMITE_TITULO);

    return $corte === null ? $title : $corte;
}
add_filter('the_title', 'bahia_limites_titulo', 20, 2);

function bahia_limites_resumo($excerpt, $post = null) {
    if (!is_string($excerpt) || $excerpt === '' || bahia_limites_contexto_isento()) {
        return $excerpt;
    }
    $post_id = ($post instanceof WP_Post) ? $post->ID : $post;
    if (bahia_limites_e_o_post_da_pagina($post_id)) {
        return $excerpt;
    }

    $corte = bahia_limites_cortar($excerpt, BAHIA_LIMITE_RESUMO);

    return $corte === null ? $excerpt : $corte;
}
add_filter('get_the_excerpt', 'bahia_limites_resumo', 20, 2);

// Pré-corte por palavras antes do corte fino por caractere: evita o wp_trim_words
// mastigar um post inteiro só para o filtro acima jogar fora quase tudo.
// ~30 palavras dão folga sobre os 160 caracteres em qualquer texto realista.
add_filter('excerpt_length', function () {
    return bahia_limites_contexto_isento() ? 55 : 30;
}, 999);

add_filter('excerpt_more', function ($more) {
    return bahia_limites_contexto_isento() ? $more : '…';
}, 999);

/**
 * Resumos dos cards do TagDiv.
 *
 * Os filtros acima não chegam aqui: td_module::get_excerpt() devolve post_excerpt cru
 * quando existe e, quando não existe, chama td_util::excerpt() direto sobre
 * post_content — nenhum dos dois caminhos passa por get_the_excerpt nem por
 * excerpt_length. Como get_excerpt() devolve post_excerpt sem mexer, preencher esse
 * campo no objeto do card é o ponto único onde dá para impor o limite.
 *
 * A escrita é no WP_Post que o módulo vai renderizar. Não persiste no banco e fica
 * restrita ao request; o post da própria página do single é pulado para o Yoast não
 * ver um resumo encurtado onde ele usa o campo como fallback.
 */
function bahia_limites_td_module($module, $post = null) {
    if (!$post instanceof WP_Post || bahia_limites_contexto_isento()) {
        return $module;
    }
    if (bahia_limites_e_o_post_da_pagina($post->ID)) {
        return $module;
    }

    $bruto = ($post->post_excerpt !== '') ? $post->post_excerpt : $post->post_content;
    if ($bruto === '') {
        return $module;
    }

    // 3000 caracteres bastam de sobra para 160 depois da limpeza, e evitam varrer um
    // post inteiro por card.
    $bruto = mb_substr($bruto, 0, 3000, 'UTF-8');
    $bruto = preg_replace('/\[caption[^\]]*\].*?\[\/caption\]/is', '', $bruto);
    $bruto = preg_replace('/<figcaption\b.*?<\/figcaption>/is', '', $bruto);
    $bruto = preg_replace('/<!--.*?-->/s', '', $bruto);   // delimitadores de bloco do Gutenberg
    $bruto = preg_replace('/\[[^\]]*\]/', '', $bruto);    // shortcodes, mantendo o texto ao redor

    $corte = bahia_limites_cortar($bruto, BAHIA_LIMITE_RESUMO);
    if ($corte !== null) {
        $post->post_excerpt = $corte;
        return $module;
    }

    // Já cabia: normaliza mesmo assim, senão o card cairia no corte por palavras do
    // TagDiv e o limite não valeria para os textos curtos.
    $limpo = trim(preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($bruto), ENT_QUOTES, 'UTF-8')));
    if ($limpo !== '') {
        $post->post_excerpt = htmlspecialchars($limpo, ENT_QUOTES, 'UTF-8', false);
    }

    return $module;
}
add_filter('td_wp_booster_module_constructor', 'bahia_limites_td_module', 10, 2);
