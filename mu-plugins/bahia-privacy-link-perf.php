<?php
/**
 * Plugin Name: Bahia.ba - Link de privacidade do rodapé sem varredura
 * Description: Neutraliza a WP_Query degenerada que o tagDiv dispara em TODA página para
 *              descobrir o link da política de privacidade.
 *
 * O defeito, em plugins/td-composer/legacy/common/wp_booster/td_util.php:4890:
 *
 *     $policy_page_id = (int) get_option( 'wp_page_for_privacy_policy' );
 *     if ( ( new WP_Query(['post_type' => 'any', 'p' => $policy_page_id]) )->found_posts == 0 ) {
 *         return '';
 *     }
 *
 * Quando a option vale 0 — que é o caso aqui —, o `p => 0` é IGNORADO pelo WP_Query, e o que
 * sobra é "todos os posts de qualquer tipo", com SQL_CALC_FOUND_ROWS para calcular found_posts.
 * Sobre 271 mil linhas e 29 post types isso custa 7-9 s, e roda em cada render de cada página,
 * porque `td_util::parse_footer_texts` monta o array de substituições chamando esta função
 * INCONDICIONALMENTE, mesmo quando o texto do rodapé não contém `##privacy_policy##`.
 *
 * Foi uma das duas causas do 504 na virada de 18/08/2026 (ver
 * scratchpad/INCIDENTE-virada-abortada-20260818.md).
 *
 * A saída da função NÃO muda. Com a option em 0, mesmo que a consulta encontre 271 mil posts,
 * `get_privacy_policy_url()` devolve '' e `$page_title` fica vazio, então o `if` seguinte é
 * falso e a função retorna '' do mesmo jeito. A varredura nunca teve efeito sobre o resultado.
 *
 * A correção aponta o `p` para um ID que não pode existir. A consulta vira uma busca por chave
 * primária (`WHERE ID = 9223372036854775807`), responde em microssegundos, devolve
 * `found_posts = 0`, e a função sai pelo mesmo `return ''` — só que sem varrer a tabela.
 *
 * Casos preservados de propósito:
 *  - option apontando para uma página REAL: `p` não é 0, o filtro não age, e a consulta por ID
 *    (que sempre foi barata) roda como antes — o link continua saindo no rodapé;
 *  - option apontando para página INEXISTENTE: `p` não é 0, o filtro não age, a consulta por ID
 *    devolve 0, e a função retorna '' — o mesmo de hoje, sem fatal.
 *
 * @author bahia.ba / Claude Code
 */

if (!defined('ABSPATH')) {
    exit;
}

/** ID impossível: acima do teto de bigint(20) usado em wp_posts.ID. */
const BAHIA_PLP_ID_IMPOSSIVEL = 9223372036854775807;

add_action('pre_get_posts', 'bahia_plp_neutraliza_varredura', 1);
function bahia_plp_neutraliza_varredura($q) {
    if (!$q instanceof WP_Query || $q->is_main_query()) {
        return;
    }

    // A assinatura exata da consulta do td_util: post_type 'any' e p ausente/zero.
    // Qualquer outra combinação passa incólume.
    if ($q->get('post_type') !== 'any') {
        return;
    }
    if ((int) $q->get('p') !== 0) {
        return;   // há um ID de verdade: a consulta e barata, deixa passar
    }

    // Só quando a option também está vazia — é o cenário que degenera.
    if ((int) get_option('wp_page_for_privacy_policy') !== 0) {
        return;
    }

    $q->set('p', BAHIA_PLP_ID_IMPOSSIVEL);
    $q->set('no_found_rows', true);
    $q->set('posts_per_page', 1);
    $q->set('fields', 'ids');
    $q->set('ignore_sticky_posts', true);
}
