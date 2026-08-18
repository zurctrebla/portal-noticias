<?php
/**
 * Plugin Name: Bahia.ba - Archive de editoria sem contar o acervo inteiro
 * Description: Troca o SQL_CALC_FOUND_ROWS da consulta principal dos archives de editoria por
 *              um post extra. "Existe próxima página?" é o que o rodapé de paginação pergunta,
 *              e o post extra responde em tempo constante.
 *
 * O problema: o archive de /politica/ monta a consulta principal com SQL_CALC_FOUND_ROWS, o que
 * obriga o MySQL a contar as 78.170 linhas publicadas da editoria só para saber o total — e o
 * total não é exibido em lugar nenhum. Com cache frio e requisições simultâneas isso empilha:
 * foi a segunda das duas causas do 504 na virada de 18/08/2026 (a primeira era a consulta do
 * rodapé, resolvida em bahia-privacy-link-perf.php).
 *
 * Medido em homolog, 30 requisições simultâneas em URLs frias:
 *   base            — mediana 28,56s | Threads_running 22 | SQL_CALC_FOUND_ROWS 19
 *   só o rodapé     — mediana 17,21s | Threads_running 12 | SQL_CALC_FOUND_ROWS 10
 *
 * O que NÃO dá para fazer é só ligar `no_found_rows`: o `bahia-scroll-infinito.php` lê
 * `found_posts` e `max_num_pages` da consulta principal (linhas 234-235) para decidir se mostra
 * o botão "Ver mais" e onde o scroll para. Zerados, o botão some. Por isso os dois são
 * PREENCHIDOS a partir do post extra — não com o total real, que ninguém usa, mas com o mínimo
 * que faz a decisão sair certa: uma página a mais quando há continuação, o número exato da
 * página corrente quando acabou.
 *
 * O post extra é descartado antes de chegar ao loop, senão cada página mostraria um item a mais.
 *
 * @author bahia.ba / Claude Code
 */

if (!defined('ABSPATH')) {
    exit;
}

const BAHIA_ACP_FLAG = 'bahia_acp_por_pagina';

/** Os archives cobertos: post type archive de editoria e as taxonomias dela. */
function bahia_acp_query_alvo($q) {
    if (!$q instanceof WP_Query || !$q->is_main_query() || is_admin()) {
        return false;
    }
    if (!function_exists('bahia_editorias_map')) {
        return false;
    }
    $editorias = array_keys(bahia_editorias_map());

    if (!empty($q->is_post_type_archive)) {
        $pt = $q->get('post_type');
        $pt = is_array($pt) ? reset($pt) : $pt;
        return in_array($pt, $editorias, true);
    }

    if (!empty($q->is_tax)) {
        $tax = $q->get('taxonomy');
        if (!$tax) {
            return false;
        }
        foreach ($editorias as $e) {
            if ($tax === $e . '_cat' || $tax === $e . '_tag') {
                return true;
            }
        }
    }

    return false;
}

add_action('pre_get_posts', 'bahia_acp_pede_um_a_mais', 99);
function bahia_acp_pede_um_a_mais($q) {
    if (!bahia_acp_query_alvo($q)) {
        return;
    }
    if ($q->get('no_found_rows')) {
        return;   // alguem ja resolveu; nao interfere
    }

    $por_pagina = (int) $q->get('posts_per_page');
    if ($por_pagina <= 0) {
        $por_pagina = (int) get_option('posts_per_page', 10);
    }
    if ($por_pagina < 1) {
        return;
    }

    $q->set('no_found_rows', true);
    $q->set('posts_per_page', $por_pagina + 1);
    $q->set(BAHIA_ACP_FLAG, $por_pagina);
}

add_filter('the_posts', 'bahia_acp_corta_extra_e_preenche', 10, 2);
function bahia_acp_corta_extra_e_preenche($posts, $q) {
    if (!$q instanceof WP_Query) {
        return $posts;
    }
    $por_pagina = (int) $q->get(BAHIA_ACP_FLAG);
    if ($por_pagina < 1) {
        return $posts;
    }

    $pagina = max(1, (int) $q->get('paged'));

    if (count($posts) > $por_pagina) {
        array_pop($posts);                                  // o extra nunca chega ao loop
        $q->found_posts   = $pagina * $por_pagina + 1;       // "ha mais uma pagina"
        $q->max_num_pages = $pagina + 1;
    } else {
        $q->found_posts   = ($pagina - 1) * $por_pagina + count($posts);
        $q->max_num_pages = $pagina;
    }

    // devolve posts_per_page ao valor real, para quem o leia depois do loop
    $q->set('posts_per_page', $por_pagina);
    $q->post_count = count($posts);

    return $posts;
}
