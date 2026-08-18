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
 * Qual bloco de destaque a home usa hoje, e quantos posts ele aceita.
 *
 * NÃO fixar o nome do bloco. A versão anterior procurava `td_block_big_grid_flex_5`
 * porque era o bloco da home de então; a home foi reconstruída, passou a usar
 * `td_block_big_grid_flex_2`, e o filtro deixou de casar — **em silêncio**. O resultado
 * foi o hero voltar ao automático por data: matéria publicada ia direto para o destaque
 * principal, passando por cima da escolha do editor.
 *
 * Por isso a busca agora é pelo PRIMEIRO `td_block_big_grid_flex_<n>` do conteúdo, seja
 * qual for o número, e o limite sai do POST_LIMIT da própria classe do bloco.
 *
 * @return array|null  [nome, limite] ou null se a home não tiver bloco de destaque.
 */
function bahia_home_destaques_bloco($content) {
    if (!preg_match('/\[(td_block_big_grid_flex_\d+)\b/', $content, $m)) {
        return null;
    }

    $classe = $m[1];
    $limite = 5;

    // O POST_LIMIT é a fonte da verdade: flex_2 aceita 5, flex_5 aceitava 4.
    if (class_exists($classe) && defined($classe . '::POST_LIMIT')) {
        $limite = (int) constant($classe . '::POST_LIMIT');
    }

    return array($classe, max(1, $limite));
}

/**
 * IDs escolhidos manualmente: hero (options_slider_m1) + laterais
 * (options_semi_destaques_m1), deduplicados, apenas publicados.
 *
 * São 1 + 4 = 5 na Options page, que é exatamente o POST_LIMIT do bloco em uso —
 * a seleção do editor preenche o destaque inteiro, sem sobra nem falta.
 *
 * @param int $limite Quantos o bloco aceita.
 */
function bahia_home_destaques_ids($limite = 5) {
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

    // só publicados, até o limite do bloco
    $out = array();
    foreach ($ids as $id) {
        if (count($out) >= $limite) {
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

    $bloco = bahia_home_destaques_bloco($content);
    if ($bloco === null) {
        // Antes isto era um `return` mudo, e foi assim que a quebra passou despercebida.
        // O comentário só sai no caso anômalo, e aparece em "ver código-fonte".
        return $content . "\n<!-- bahia-home-destaques: nenhum td_block_big_grid_flex_* na home; destaque manual NAO aplicado -->\n";
    }
    list($classe, $limite) = $bloco;

    $ids = bahia_home_destaques_ids($limite);
    if (empty($ids)) {
        return $content; // sem seleção manual -> mantém comportamento automático
    }
    $done = true;

    $post_ids   = implode(',', $ids);
    $post_types = bahia_home_destaques_all_post_types();

    $content = preg_replace_callback(
        '/\[' . preg_quote($classe, '/') . '\b[^\]]*\]/',
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
