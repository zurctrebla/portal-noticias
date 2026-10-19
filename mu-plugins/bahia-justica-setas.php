<?php
/**
 * Plugin Name: Bahia.ba - Setinhas de rolagem no bloco Justiça da home (KAN-13)
 * Description: O bloco Justiça da home é um `td_block_big_grid_flex_1` (grade de 4 cards).
 *              Diferente dos flex blocks das outras editorias, o render() desse bloco nunca
 *              chama get_block_pagination() nem get_block_js(): o atributo `ajax_pagination`
 *              fica inerte e não existe `tdBlock` registrado para o handler do TagDiv.
 *              Este arquivo renderiza o bloco pelo próprio TagDiv e acrescenta as duas peças
 *              que faltam, no mesmo lugar em que os flex blocks as colocam (dentro do wrapper,
 *              no fim). A troca de página é o admin-ajax nativo (td_ajax_block), que já sabe
 *              renderizar a grade via inner() — o layout da seção não muda.
 *              As cores das setas já vêm de bahia-editoria-tags.php (:has(.td-cpt-justica)).
 *              Reversível: remover este arquivo volta à grade sem setas.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

add_filter('pre_do_shortcode_tag', 'bahia_justica_setas_render', 10, 4);
function bahia_justica_setas_render($saida, $tag, $atts, $m) {
    if ($saida !== false || $tag !== 'td_block_big_grid_flex_1' || !class_exists('td_block_big_grid_flex_1')) {
        return $saida;
    }

    $atts = (array) $atts;

    // Só o bloco de Justiça: é o único big grid da home e o único que o ticket pede.
    if (!isset($atts['installed_post_types']) || trim($atts['installed_post_types']) !== 'justica') {
        return $saida;
    }

    // Liga a paginação nos atts: get_block_classes() passa a emitir td_with_ajax_pagination,
    // o bahia-td-query-perf.php pede o post extra que decide a seta "próxima", e o handler
    // ajax recebe o mesmo valor de volta pelo tdBlock.
    $atts['ajax_pagination'] = 'next_prev';

    $bloco = new td_block_big_grid_flex_1();
    $html  = $bloco->render($atts, isset($m[5]) ? $m[5] : null);

    // Editor ao vivo / requisição do composer: get_block_js() devolve vazio; sem o tdBlock a
    // seta não faria nada, então nem a mostramos.
    $js = $bloco->get_block_js();
    if ($js === '' || $bloco->block_uid === '') {
        return $html;
    }

    $fim = strrpos($html, '</div>');
    if ($fim === false) {
        return $html;
    }

    return substr($html, 0, $fim) . $js . $bloco->get_block_pagination() . substr($html, $fim);
}
