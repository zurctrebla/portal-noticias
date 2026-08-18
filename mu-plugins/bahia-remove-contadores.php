<?php
/**
 * Plugin Name: Bahia.ba - Esconde contadores sem interruptor no painel
 * Description: Complemento CSS do bahia-td-opcoes.php. Aquele desliga, pela via nativa
 *              do tema, o ícone de olho (views) e o de balão (comentários) dos cards e
 *              do post individual. Sobra o "0 POSTS / 0 COMENTÁRIOS" da caixa de autor,
 *              que o td-cloud-library escreve cravado no PHP
 *              (shortcodes/author/tdb_author_box.php:380) sem atributo para desligar —
 *              e o arquivo do plugin não pode ser editado sem quebrar o deploy de
 *              produção. Daí o CSS.
 *
 *              Aparece na página de autor e na caixa de autor do post individual.
 *
 *              O mesmo vale para os archives de editoria: o loop-archive.php do tema
 *              chama comments_number() direto, sem passar pela opção
 *              `tds_m_show_comments` — por isso `.td-module-comments` também entra
 *              aqui, senão o balão volta a aparecer nos cards do arquivo.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    $css = <<<CSS
.tdb-author-counters{display:none !important;}
.td-module-comments,
.td-post-comments,
.td-module-views,
.td-post-views{display:none !important;}
CSS;
    wp_register_style('bahia-sem-contadores', false, array(), '1.0.0');
    wp_enqueue_style('bahia-sem-contadores');
    wp_add_inline_style('bahia-sem-contadores', $css);
}, 40);
