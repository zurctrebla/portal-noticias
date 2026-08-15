<?php
/**
 * Plugin Name: Bahia.ba - Opções do painel tagDiv fixadas em código
 * Description: Fixa, em runtime, opções do painel do tema Newspaper que precisam valer
 *              sempre — sem gravar no banco. Motivo: o painel do tagDiv reescreve o
 *              option `td_011` inteiro a cada save, então ajuste feito só pela interface
 *              volta atrás sozinho. Já aconteceu com o Pinterest, removido numa rodada
 *              anterior e reaparecido depois.
 *
 *              O que é fixado:
 *                - td_social_drag_and_drop.pinterest = false  → tira o Pinterest da barra
 *                  de compartilhamento do post individual. Facebook, X e WhatsApp seguem
 *                  ligados e já compartilham a URL da matéria (conferido no HTML gerado).
 *                  Instagram não entra: não existe endpoint público de share de link a
 *                  partir do navegador.
 *                - tds_m_show_comments / tds_p_show_comments / tds_p_show_views = 'hide'
 *                  → some com o ícone de balão (comentários) e o de olho (views) nos
 *                  cards e no post individual. Comentários estão desligados no site,
 *                  então esses números são sempre zero.
 *
 *              Sobre o momento do registro: o nome do option vem da constante
 *              TD_THEME_OPTIONS_NAME, definida em
 *              themes/Newspaper/includes/tagdiv-config.php — ou seja, pelo TEMA. Mas o
 *              próprio functions.php do tema já lê opções enquanto carrega, e
 *              td_options::read_from_db() guarda o resultado num static
 *              (td_options::$td_options). Quem registrar o filtro depois disso não pega
 *              mais nada: a leitura já aconteceu e ficou em cache.
 *
 *              Por isso o filtro é registrado JÁ NO CARREGAMENTO deste mu-plugin — o
 *              ponto mais cedo possível, antes de plugins e tema — com o nome do option
 *              em constante local. Como isso duplica um valor que é do tema, o
 *              after_setup_theme confere se a constante bate e registra também o nome
 *              real caso o tema mude de option numa atualização.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

/**
 * Nome do option de tema do tagDiv (= TD_THEME_OPTIONS_NAME). Duplicado aqui de
 * propósito: precisamos filtrar antes de o tema existir. Conferido em runtime abaixo.
 */
const BAHIA_TD_OPTION_NAME = 'td_011';
if (!defined('ABSPATH')) {
    exit;
}

add_filter('option_' . BAHIA_TD_OPTION_NAME, 'bahia_td_opcoes_forcar');

// Rede de segurança: se o tema passar a usar outro option, filtra o nome real também.
add_action('after_setup_theme', function () {
    if (defined('TD_THEME_OPTIONS_NAME') && TD_THEME_OPTIONS_NAME !== BAHIA_TD_OPTION_NAME) {
        add_filter('option_' . TD_THEME_OPTIONS_NAME, 'bahia_td_opcoes_forcar');
    }
}, 0);

function bahia_td_opcoes_forcar($options) {
    if (!is_array($options)) {
        return $options;
    }

    // Item 12 — Pinterest fora da barra de compartilhamento.
    if (isset($options['td_social_drag_and_drop']) && is_array($options['td_social_drag_and_drop'])) {
        $options['td_social_drag_and_drop']['pinterest'] = false;
    }

    // Item 6 — contadores de comentário e visualização.
    $options['tds_m_show_comments'] = 'hide';
    $options['tds_p_show_comments'] = 'hide';
    $options['tds_p_show_views']    = 'hide';

    // Rodada 10, item 3 — fora a textura de mosaico das faixas laterais.
    //
    // O mosaico é `background-image` do <body>, e vinha de tds_site_background_image
    // (um bg.png no CloudFront). Some aqui, em runtime, e não por escrita no banco:
    // assim a mudança viaja junto com o código para produção e o td_011 do banco não
    // precisa entrar no inventário de migração por causa disto.
    //
    // O site continua em td-boxed-layout — o que muda é só a cor por baixo da caixa.
    // A barra de menu passa a ir até as bordas por CSS, em bahia-cabecalho-r10.php.
    $options['tds_site_background_image'] = '';
    $options['tds_site_background_color'] = '#ffffff';

    return $options;
}
