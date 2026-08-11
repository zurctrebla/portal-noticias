<?php
/**
 * Plugin Name: Bahia.ba - Azul do site nos controles de UI
 * Description: Troca o verde-água do demo Magazine PRO (#008d7f) pelo azul institucional
 *              do bahia.ba (#15559E) em dois controles — e só nesses dois, de propósito:
 *                - o botão flutuante "voltar ao topo" (.td-scroll-up);
 *                - o botão "VER MAIS NOTÍCIAS" que substitui a paginação numerada.
 *              Os demais botões do site ficam como estão (pedido explícito da rodada 2).
 *
 *              O #15559E é o azul da barra de menu e dos botões do tema anterior
 *              (bahia_refactor, assets/css/base.css:205 e 1142), o mesmo aplicado na
 *              barra do menu principal nesta rodada.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Azul institucional do bahia.ba. */
if (!defined('BAHIA_AZUL')) {
    define('BAHIA_AZUL', '#15559e');
}
/** Tom mais escuro para hover/active. */
if (!defined('BAHIA_AZUL_ESCURO')) {
    define('BAHIA_AZUL_ESCURO', '#0f4079');
}

add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    $azul   = BAHIA_AZUL;
    $escuro = BAHIA_AZUL_ESCURO;

    $css = <<<CSS
/* voltar ao topo — o demo trazia #008d7f */
.td-scroll-up,
.td-scroll-up:hover{background-color:{$azul} !important;}

/* Botão "VER MAIS NOTÍCIAS". Existe em dois sabores, porque as listagens do site
   são renderizadas por dois caminhos diferentes:
     .td-load-more-wrap a   -> load more nativo do tagDiv (home, busca, autor,
                               categoria, tag, data — blocos tdb_loop)
     .bahia-load-more-btn   -> botão do mu-plugin bahia-scroll-infinito, usado nos
                               archives de editoria, que são renderizados em PHP
   Só estes. As setas .td-next-prev-wrap seguem na cor da editoria (itens 3 e 4). */
.td-load-more-wrap a,
.bahia-load-more-btn{
    background-color:{$azul} !important;
    border-color:{$azul} !important;
    color:#fff !important;
    text-transform:uppercase;
}
.td-load-more-wrap a:hover,
.td-load-more-wrap a:focus,
.bahia-load-more-btn:hover,
.bahia-load-more-btn:focus{
    background-color:{$escuro} !important;
    border-color:{$escuro} !important;
    color:#fff !important;
}
CSS;

    wp_register_style('bahia-cores-ui', false, array(), '1.0.0');
    wp_enqueue_style('bahia-cores-ui');
    wp_add_inline_style('bahia-cores-ui', $css);
}, 45);
