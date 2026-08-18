<?php
/**
 * Plugin Name: Bahia TagDiv Query Perf
 * Description: Desliga o SQL_CALC_FOUND_ROWS nas queries de blocos TagDiv que NÃO paginam.
 *
 * Problema: cada bloco TagDiv monta a WP_Query com SQL_CALC_FOUND_ROWS (para saber o total e
 * renderizar paginação). Num bloco simples de "últimas notícias" (ex.: rodapé, LIMIT 3) que
 * varre todos os CPTs de editoria (~272k linhas publicadas), esse count custa ~31s — deixando
 * CADA página de post/single em ~35s. O td_block só usa `found_posts` quando `ajax_pagination`
 * está definido (load_more/numbered/next_prev/infinite); sem paginação, o count é puro
 * desperdício.
 *
 * Correção: via o filtro `td_data_source_blocks_query_args` (exposto pelo próprio TagDiv em
 * td_data_source::get_wp_query), setamos `no_found_rows = true` quando o bloco não pagina.
 * Blocos paginados mantêm o comportamento original. Sem editar plugin premium.
 *
 * @author bahia.ba / Claude Code
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ATUALIZADO em 18/08/2026, depois da virada abortada.
 *
 * A versão anterior só desligava o count nos blocos SEM paginação. Os blocos paginados
 * continuavam contando o acervo inteiro a cada render.
 *
 * Isso foi tentado uma vez e pareceu não render nada: com a consulta do rodapé ainda
 * no lugar (ver bahia-privacy-link-perf.php), ela era tão maior que escondia estas.
 * Removida aquela, o backtrace mostra os blocos como a fonte restante — e aí a
 * medição passou a mostrar o ganho. Fica o registro: a primeira medição não
 * invalidou a hipótese, só não conseguia enxergá-la.
 *
 * O que NÃO dá para fazer é só omitir a contagem: o td_block lê `found_posts` E
 * `max_num_pages` (td_block.php:3149-3181), injeta os dois em JS e esconde o botão
 * quando `1 >= ceil((found_posts - offset)/limit)`. Zerados, o botão some e o scroll
 * para. Por isso os dois são preenchidos a partir de um post extra, que é descartado
 * antes de chegar à saída.
 */

const BAHIA_TDQP_FLAG = 'bahia_tdqp_por_pagina';

add_filter('td_data_source_blocks_query_args', 'bahia_td_no_found_rows_when_no_pagination', 10, 2);
function bahia_td_no_found_rows_when_no_pagination($args, $atts) {
    if (!empty($args['no_found_rows'])) {
        return $args;
    }

    $args['no_found_rows'] = true;

    // Bloco sem paginação: found_posts não é lido no render, nada mais a fazer.
    if (empty($atts['ajax_pagination'])) {
        return $args;
    }

    $por_pagina = isset($args['posts_per_page']) ? (int) $args['posts_per_page'] : 0;
    if ($por_pagina < 1) {
        return $args;   // -1 (todos) ou ausente: não há próxima página a decidir
    }

    $args[BAHIA_TDQP_FLAG]  = $por_pagina;
    $args['posts_per_page'] = $por_pagina + 1;

    return $args;
}

add_filter('the_posts', 'bahia_td_corta_post_extra', 10, 2);
function bahia_td_corta_post_extra($posts, $q) {
    if (!$q instanceof WP_Query) {
        return $posts;
    }
    $por_pagina = (int) $q->get(BAHIA_TDQP_FLAG);
    if ($por_pagina < 1) {
        return $posts;
    }

    $pagina = max(1, (int) $q->get('paged'));

    if (count($posts) > $por_pagina) {
        array_pop($posts);
        $q->found_posts   = $pagina * $por_pagina + 1;
        $q->max_num_pages = $pagina + 1;
    } else {
        $q->found_posts   = ($pagina - 1) * $por_pagina + count($posts);
        $q->max_num_pages = $pagina;
    }

    $q->set('posts_per_page', $por_pagina);
    $q->post_count = count($posts);

    return $posts;
}
