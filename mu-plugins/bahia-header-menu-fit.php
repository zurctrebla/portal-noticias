<?php
/**
 * Plugin Name: Bahia.ba - Menu do header em uma linha (desktop)
 * Description: Reduz levemente a fonte (14px -> 13px) e o espaçamento lateral
 *              (0 14px -> 0 11px) dos itens do menu principal do header (tdb_header_menu)
 *              no desktop, o suficiente para os 10 itens caberem em UMA linha
 *              (antes "Quem Somos" quebrava para a segunda linha). Só afeta desktop
 *              (>=1019px); no mobile/tablet o menu vira hambúrguer e não é tocado.
 *              Reversível: basta remover este arquivo para voltar ao estado atual
 *              (duas linhas, fonte 14px).
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

function bahia_header_menu_fit_css() {
    return <<<CSS
@media (min-width: 1019px){
    .tdb_header_menu .menu-item > a{
        font-size:13px !important;
        padding-left:11px !important;
        padding-right:11px !important;
    }
}
CSS;
}

function bahia_header_menu_fit_enqueue() {
    if (is_admin()) {
        return;
    }
    wp_register_style('bahia-header-menu-fit', false, array(), '1.0.0');
    wp_enqueue_style('bahia-header-menu-fit');
    wp_add_inline_style('bahia-header-menu-fit', bahia_header_menu_fit_css());
}
add_action('wp_enqueue_scripts', 'bahia_header_menu_fit_enqueue', 40);
