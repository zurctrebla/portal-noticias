<?php
/**
 * Plugin Name: Bahia.ba - Painel sem a caixa "Custom Post Type - Settings"
 * Description: O td-composer põe na edição de toda editoria (CPT) a caixa "Custom Post
 *              Type - Settings" (taxonomia de autoload, taxonomia primária, sidebar,
 *              subtítulo), acima do Yoast SEO. A redação não usa nada dela (KAN-29):
 *              o subtítulo do site é o campo ACF `subtitulo` (ver bahia-subtitulo.php) e
 *              o layout vem dos templates do tema. Este arquivo só tira a caixa da tela.
 *
 *              Sem risco para os dados: os valores já gravados (meta
 *              td_post_theme_settings) continuam no banco e continuam valendo no site, e
 *              o salvamento do tagDiv (WPAlchemy_MetaBox::_save) exige o nonce da
 *              própria caixa — sem ela na tela, ele não grava nem apaga nada.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * A caixa é registrada no admin_init (WPAlchemy, prioridade 9) com o id
 * td_post_theme_settings_metabox, contexto normal. add_meta_boxes roda depois, na
 * montagem da tela de edição, e é onde remove_meta_box tem efeito.
 */
add_action('add_meta_boxes', function ($post_type) {
    // Páginas não recebem esta caixa (são tipo nativo); a remoção é só nos CPTs.
    if (in_array($post_type, array('post', 'page'), true)) {
        return;
    }
    foreach (array('normal', 'advanced', 'side') as $contexto) {
        remove_meta_box('td_post_theme_settings_metabox', $post_type, $contexto);
    }
}, 999);
