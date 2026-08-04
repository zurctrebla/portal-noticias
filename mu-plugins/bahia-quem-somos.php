<?php
/**
 * Plugin Name: Bahia.ba - Página Quem Somos (layout da equipe)
 * Description: Estilos da página "Quem Somos" (slug quem-somos): texto institucional +
 *              grade da equipe por cargo, com placeholder de avatar (foto pendente).
 *              O CSS fica aqui (e não inline no conteúdo) porque o KSES do WordPress
 *              remove `display:` e tags <svg> do post_content — então flex e o ícone
 *              do avatar precisam vir do stylesheet. Também desliga o wpautop nessa
 *              página (o HTML já é estruturado). Cores de marca: #13182B, #4371B6, #EDECED.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

// wpautop atrapalha o HTML estruturado da página -> desliga só nela.
add_action('wp', function () {
    if (is_page('quem-somos')) {
        remove_filter('the_content', 'wpautop');
        remove_filter('the_content', 'shortcode_unautop');
    }
});

function bahia_qs_css() {
    // Silhueta do avatar via data-URI (fica no CSS -> não é removida pelo KSES).
    $svg  = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='#b8c0cc'>";
    $svg .= "<circle cx='12' cy='8.4' r='4.2'/><path d='M3.6 20.4c0-4.3 4.2-6.4 8.4-6.4s8.4 2.1 8.4 6.4v.9H3.6z'/></svg>";
    $data = 'data:image/svg+xml,' . rawurlencode($svg);

    return <<<CSS
.qs-intro{max-width:820px;margin:0 auto 42px;font-size:17px;line-height:1.7;color:#333;}
.qs-intro p{margin:0 0 18px;}
.qs-equipe{max-width:980px;margin:0 auto 30px;}
.qs-equipe .qs-title{text-align:center;color:#13182B !important;font-size:26px;margin:0 0 8px;}
.qs-equipe .qs-sub{text-align:center;color:#8a8f98;font-size:14px;margin:0 0 34px;}
.qs-role{color:#4371B6 !important;font-size:18px;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid #EDECED;padding-bottom:8px;margin:34px 0 22px;}
.qs-grid{display:flex;flex-wrap:wrap;gap:28px 22px;justify-content:flex-start;margin:0 0 6px;}
.qs-member{width:132px;text-align:center;}
.qs-avatar{display:block;width:96px;height:96px;border-radius:50%;background:#EDECED url("$data") no-repeat center;background-size:60px 60px;margin:0 auto 10px;}
.qs-name{display:block;font-weight:600;font-size:14px;color:#13182B !important;line-height:1.3;}
.qs-cargo{display:block;font-size:12px;color:#8a8f98;margin-top:2px;}
@media (max-width:767px){.qs-grid{justify-content:center;gap:22px;}}
.qs-contact{max-width:820px;margin:34px auto 10px;text-align:center;font-size:17px;line-height:1.7;color:#333;}
.qs-contact a{color:#4371B6 !important;font-weight:600;text-decoration:none;}
.qs-contact a:hover{text-decoration:underline;}
CSS;
}

add_action('wp_enqueue_scripts', function () {
    if (is_admin() || !is_page('quem-somos')) {
        return;
    }
    wp_register_style('bahia-qs', false, array(), '1.0.0');
    wp_enqueue_style('bahia-qs');
    wp_add_inline_style('bahia-qs', bahia_qs_css());
}, 40);
