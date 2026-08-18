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

/**
 * INCIDENTE DE 18/08/2026, À TARDE — por que a assinatura é conferida ARGUMENTO A ARGUMENTO.
 *
 * A primeira versão deste arquivo reconhecia a consulta do td_util por dois traços:
 * `post_type` igual a 'any' e `p` ausente ou zero. Isso não é uma assinatura, é uma
 * coincidência comum: QUALQUER consulta secundária que use 'any' e não passe um `p` cai
 * nela — inclusive as legítimas, que então recebem `p = PHP_INT_MAX` e voltam vazias.
 *
 * Foi o que aconteceu com o bloco "+ Mais Lidas". O `render()` do
 * mu-plugins/bahia-mais-lidas.php monta `post__in` + `post_type => 'any'`; a lista sumiu
 * inteira da home de homologação às 09:26 UTC, quando a revisão 65 subiu com a correção do
 * 504. O `mais_lidas2()` do bahia_refactor (themes/bahia_refactor/functions.php:1080) monta
 * a MESMA consulta e é o caminho primário da lista em produção — lá o defeito não apareceu
 * só porque o transient do GA4 estava vazio e a função caiu no fallback SQL sozinha.
 *
 * A consulta que se quer neutralizar é literalmente esta, e nada além dela:
 *
 *     new WP_Query(['post_type' => 'any', 'p' => $policy_page_id])
 *
 * Dois argumentos. Por isso a conferência é sobre `$q->query` — os argumentos COMO FORAM
 * PASSADOS, antes de o WP preencher os padrões —, e não sobre query_vars já normalizados.
 * Uma consulta com `post__in`, `post_status`, `posts_per_page` ou qualquer outra coisa é
 * outra consulta e passa incólume, ainda que também use 'any'.
 *
 * Contrapartida assumida: se o tagDiv um dia acrescentar um argumento à chamada, a
 * assinatura deixa de bater e a varredura volta a rodar — o site fica lento de novo, não
 * quebrado. É o lado certo para errar, e o sintoma (páginas em 7-9 s) é o mesmo do 504 de
 * 18/08, já documentado em scratchpad/INCIDENTE-virada-abortada-20260818.md.
 */
const BAHIA_PLP_ASSINATURA = array('p', 'post_type');

add_action('pre_get_posts', 'bahia_plp_neutraliza_varredura', 1);
function bahia_plp_neutraliza_varredura($q) {
    if (!$q instanceof WP_Query || $q->is_main_query()) {
        return;
    }

    if ($q->get('post_type') !== 'any') {
        return;
    }

    // Assinatura exata: exatamente `post_type` e `p`, nada mais.
    $passados = array_keys((array) $q->query);
    sort($passados);
    if ($passados !== BAHIA_PLP_ASSINATURA) {
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
