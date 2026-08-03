<?php
/**
 * Plugin Name: Bahia.ba - Layout do header em duas linhas (desktop)
 * Description: No desktop (>=1019px), organiza o header em DUAS linhas:
 *                Linha 1: menu de editorias (13px) com os 10 itens em uma única linha
 *                Linha 2: ícones de redes sociais à ESQUERDA + lupa de busca à DIREITA
 *              Os 3 blocos do header (tdb_header_menu, tdm_block_socials,
 *              tdb_header_search) ficam num wrapper comum; usamos flexbox com
 *              flex-wrap: o menu ocupa 100% (linha 1) e os sociais+lupa quebram p/ a
 *              linha 2 (sociais à esquerda; lupa empurrada à direita via margin-left:auto).
 *              A fonte do menu é reduzida de 14px -> 13px só o suficiente p/ os 10 itens
 *              caberem em uma linha (mantendo legibilidade). Só desktop; no mobile/tablet
 *              o header é hambúrguer e não é tocado. Reversível: remover este arquivo
 *              volta ao layout empilhado original (sociais em cima, menu, lupa).
 * Version: 2.1.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

function bahia_header_menu_fit_css() {
    return <<<CSS
@media (min-width: 1019px){
    /* wrapper comum dos 3 blocos do header -> flex com wrap (2 linhas) */
    .wpb_wrapper:has(> .tdb_header_menu):has(> .tdb_header_search){
        display:flex !important;
        flex-wrap:wrap !important;
        align-items:center !important;
    }
    /* Linha 1: menu ocupa 100% da largura */
    .wpb_wrapper:has(> .tdb_header_menu) > .tdb_header_menu{
        order:1;
        flex:1 1 100% !important;
    }
    /* menu 13px p/ os 10 itens caberem em uma linha */
    .tdb_header_menu .menu-item > a{
        font-size:13px !important;
        padding-left:11px !important;
        padding-right:11px !important;
    }
    /* Linha 2: ícones sociais à esquerda */
    .wpb_wrapper:has(> .tdb_header_menu) > .tdm_block_socials{
        order:2;
        flex:0 0 auto !important;
        margin-left:0 !important;
        margin-bottom:0 !important;
    }
    /* Linha 2: lupa à direita */
    .wpb_wrapper:has(> .tdb_header_menu) > .tdb_header_search{
        order:3;
        flex:0 0 auto !important;
        margin-left:auto !important;
    }
}
CSS;
}

function bahia_header_menu_fit_enqueue() {
    if (is_admin()) {
        return;
    }
    wp_register_style('bahia-header-menu-fit', false, array(), '2.1.0');
    wp_enqueue_style('bahia-header-menu-fit');
    wp_add_inline_style('bahia-header-menu-fit', bahia_header_menu_fit_css());
}
add_action('wp_enqueue_scripts', 'bahia_header_menu_fit_enqueue', 40);
