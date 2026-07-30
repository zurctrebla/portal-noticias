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

add_filter('td_data_source_blocks_query_args', 'bahia_td_no_found_rows_when_no_pagination', 10, 2);
function bahia_td_no_found_rows_when_no_pagination($args, $atts) {
    // Só quando o bloco não tem paginação (found_posts não é usado no render).
    if (empty($atts['ajax_pagination'])) {
        if (empty($args['no_found_rows'])) {
            $args['no_found_rows'] = true;
        }
    }
    return $args;
}
