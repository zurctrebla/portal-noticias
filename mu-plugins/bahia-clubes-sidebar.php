<?php
/**
 * Plugin Name: Bahia.ba - Boxes EC Bahia / EC Vitória (sidebar)
 * Description: Shortcode [bahia_clubes_sidebar] com os boxes de último/próximo jogo
 *              do EC Bahia e do EC Vitória (mesma API football-data.org, mesmos team
 *              IDs 1777/1782 e escudos locais da página Brasileirão 2026), empilhados
 *              verticalmente para a sidebar da home. Reaproveita as funções
 *              bahia_fut_clube_jogos_dados() e bahia_fut_render_box_clube() do
 *              mu-plugin bahia-futebol-display.php.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

function bahia_clubes_sidebar_css() {
    static $done = false;
    if ($done) {
        return '';
    }
    $done = true;
    return <<<CSS
<style>
/* 48px = ritmo vertical padrão dos blocos da home (valor predominante medido) */
.bahia-cl-sidebar{--bahia:#0a58ca;--vitoria:#c8102e;margin:0 0 48px}
.bahia-cl-sidebar .bahia-cl-box{width:100%;min-width:0;border:1px solid #e5e5e5;border-radius:4px;overflow:hidden;background:#fff;margin-bottom:16px}
.bahia-cl-sidebar .bahia-cl-box:last-child{margin-bottom:0}
.bahia-cl-sidebar .bahia-cl-head{color:#fff;font-weight:700;font-size:14px;letter-spacing:.03em;text-transform:uppercase;padding:9px 14px}
.bahia-cl-sidebar .bahia-cl-box.bahia .bahia-cl-head{background:var(--bahia)}
.bahia-cl-sidebar .bahia-cl-box.vitoria .bahia-cl-head{background:var(--vitoria)}
.bahia-cl-sidebar .bahia-cl-body{padding:4px 14px}
.bahia-cl-sidebar .bahia-cl-jogo{padding:12px 0;border-bottom:1px solid #eee}
.bahia-cl-sidebar .bahia-cl-jogo:last-child{border-bottom:0}
.bahia-cl-sidebar .bahia-cl-rot{font-size:11px;text-transform:uppercase;letter-spacing:.03em;color:#888;font-weight:700;margin-bottom:8px}
.bahia-cl-sidebar .bahia-cl-row{display:flex;align-items:center}
.bahia-cl-sidebar .bahia-cl-lado{flex:1;display:flex;align-items:center;gap:6px;min-width:0}
.bahia-cl-sidebar .bahia-cl-lado.casa{justify-content:flex-end;text-align:right}
.bahia-cl-sidebar .bahia-cl-lado.fora{justify-content:flex-start;text-align:left}
.bahia-cl-sidebar .bahia-cl-nome{font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.bahia-cl-sidebar .bahia-cl-esc{width:26px;height:26px;object-fit:contain;flex:0 0 auto}
.bahia-cl-sidebar .bahia-cl-mid{flex:0 0 auto;min-width:52px;text-align:center;font-weight:700;font-size:17px}
.bahia-cl-sidebar .bahia-cl-x{color:#bbb;font-weight:400;margin:0 4px}
.bahia-cl-sidebar .bahia-cl-meta{font-size:11px;color:#999;margin-top:8px;text-align:center}
</style>
CSS;
}

/**
 * Boxes de último/próximo jogo do EC Bahia e do EC Vitória.
 *
 * @param bool $com_botao Inclui o botão "TABELA COMPLETA" abaixo dos boxes.
 *                        Verdadeiro na home; falso em /esporte/, onde a tabela de
 *                        classificação logo acima já traz o mesmo botão.
 */
function bahia_clubes_sidebar_boxes_html($com_botao = true) {
    if (!function_exists('bahia_fut_clube_jogos_dados') || !function_exists('bahia_fut_render_box_clube')) {
        return '';
    }
    $brasao  = content_url('/themes/bahia_refactor/brasileirao/brasao/');
    $bahia   = bahia_fut_clube_jogos_dados(1777, 'bahia_fut_ecbahia_v1');
    $vitoria = bahia_fut_clube_jogos_dados(1782, 'bahia_fut_ecvitoria_v1');

    ob_start();
    echo bahia_clubes_sidebar_css();
    echo '<div class="bahia-cl-sidebar">';
    bahia_fut_render_box_clube('EC Bahia', 'bahia', $bahia, $brasao . 'bahia.png');
    bahia_fut_render_box_clube('EC Vitória', 'vitoria', $vitoria, $brasao . 'vitoria.png');
    // Mesmo botão (rótulo, destino e estilo) da tabela de classificação em /esporte/.
    if ($com_botao && function_exists('bahia_esporte_tabela_link_html')) {
        echo bahia_esporte_tabela_link_css();
        echo bahia_esporte_tabela_link_html();
    }
    echo '</div>';
    return ob_get_clean();
}

add_shortcode('bahia_clubes_sidebar', function () {
    return bahia_clubes_sidebar_boxes_html(true);
});
