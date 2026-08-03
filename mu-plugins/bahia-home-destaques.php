<?php
/**
 * Plugin Name: Bahia.ba - Destaques manuais da home (hero)
 * Description: Faz o bloco de destaque do topo da home (td_block_big_grid_flex_5)
 *              exibir as 4 publicações escolhidas MANUALMENTE pelos editores, em
 *              vez de puxar automaticamente por data. Reaproveita exatamente a mesma
 *              fonte do tema bahia_refactor: a ACF Options page grava os IDs em
 *              wp_options —
 *                - options_slider_m1         -> hero (imagem grande, 1º id)
 *                - options_semi_destaques_m1 -> os 3 cards ao lado (ids seguintes)
 *              (mesma lógica de sliders()/semi_destaque() do bahia_refactor).
 *              Injeta esses IDs como post_ids="" no shortcode do bloco via filtro
 *              the_content (runtime, antes do do_shortcode), então trocar a seleção
 *              na Options page reflete na home sem editar o conteúdo da página.
 *              O bloco big_grid_flex_5 tem POST_LIMIT=4 e ordena por post__in, então
 *              a ordem dos ids define hero (1º) + 3 laterais.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_HOME_DESTAQUES_VER', '1.1.0');

/**
 * Todas as editorias (CPTs) — para o bloco hero aceitar destaques de qualquer
 * editoria (o post__in é filtrado por post_type IN installed_post_types; se a
 * editoria do post escolhido não estiver aqui, o card some). Reutiliza o mapa do
 * mu-plugin bahia-editorias-cpt.php quando disponível.
 */
function bahia_home_destaques_all_post_types() {
    if (function_exists('bahia_editorias_map')) {
        return implode(',', array_keys(bahia_editorias_map()));
    }
    return 'politica,salvador,dende_poder,municipios,justica,esporte,entretenimento,bahia,brasil,economia,mundo,mais_gente,entrevista,mais_noticias,artigo,especial,exclusivo,carnaval';
}

/**
 * IDs escolhidos manualmente: hero (options_slider_m1) + laterais
 * (options_semi_destaques_m1), deduplicados, apenas publicados, no máximo 4
 * (POST_LIMIT do td_block_big_grid_flex_5).
 */
function bahia_home_destaques_ids() {
    $ids = array();

    $hero = get_option('options_slider_m1');
    if (is_array($hero)) {
        foreach ($hero as $id) { $ids[] = (int) $id; }
    }

    $semis = get_option('options_semi_destaques_m1');
    if (is_array($semis)) {
        foreach ($semis as $id) { $ids[] = (int) $id; }
    }

    // dedup preservando ordem + remove zeros
    $ids = array_values(array_unique(array_filter($ids)));

    // só publicados, no máximo 4
    $out = array();
    foreach ($ids as $id) {
        if (count($out) >= 4) {
            break;
        }
        if (get_post_status($id) === 'publish') {
            $out[] = $id;
        }
    }

    return $out;
}

/**
 * Injeta os post_ids manuais no primeiro td_block_big_grid_flex_5 da home.
 * Prioridade 9 (< 11 do do_shortcode) para o bloco já receber os ids nos atts.
 */
function bahia_home_destaques_content($content) {
    static $done = false;

    if ($done || is_admin()) {
        return $content;
    }
    if (!is_front_page() && !is_home()) {
        return $content;
    }
    if (strpos($content, 'td_block_big_grid_flex_5') === false) {
        return $content;
    }

    $ids = bahia_home_destaques_ids();
    if (empty($ids)) {
        return $content; // sem seleção manual -> mantém comportamento automático
    }
    $done = true;

    $post_ids   = implode(',', $ids);
    $post_types = bahia_home_destaques_all_post_types();

    $content = preg_replace_callback(
        '/\[td_block_big_grid_flex_5\b[^\]]*\]/',
        function ($m) use ($post_ids, $post_types) {
            $sc = $m[0];

            // 1) post_ids: seleção manual (hero + 3 laterais, ordenados por post__in)
            if (preg_match('/\spost_ids="[^"]*"/', $sc)) {
                $sc = preg_replace('/\spost_ids="[^"]*"/', ' post_ids="' . $post_ids . '"', $sc, 1);
            } else {
                $sc = substr($sc, 0, -1) . ' post_ids="' . $post_ids . '"]';
            }

            // 2) installed_post_types: aceitar destaques de QUALQUER editoria
            //    (senão o post__in é cortado pelo filtro de post_type e o card some).
            if (preg_match('/\sinstalled_post_types="[^"]*"/', $sc)) {
                $sc = preg_replace('/\sinstalled_post_types="[^"]*"/', ' installed_post_types="' . $post_types . '"', $sc, 1);
            } else {
                $sc = substr($sc, 0, -1) . ' installed_post_types="' . $post_types . '"]';
            }

            return $sc;
        },
        $content,
        1
    );

    return $content;
}
add_filter('the_content', 'bahia_home_destaques_content', 9);
