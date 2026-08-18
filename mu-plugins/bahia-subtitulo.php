<?php
/**
 * Plugin Name: Bahia.ba - Subtítulo da matéria
 * Description: Restaura a exibição do subtítulo no single. A redação escreve o campo ACF
 *              `subtitulo` (271 mil matérias); o tema antigo o exibia como <h2> abaixo do
 *              H1 e a migração para o Newspaper deixou o campo de fora, sem exibi-lo em
 *              lugar nenhum. NÃO toca nos cards de listagem nem no resumo.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * O texto que vai para a tela.
 *
 * Cerca de 7% dos registros trazem tag solta herdada do editor antigo e o template do
 * tagDiv imprime o valor sem escapar (printf '%1$s'), então a limpeza e o escape têm de
 * acontecer aqui. Entidades HTML não aparecem no acervo (0 em 5.000 amostrados), mas o
 * decode fica por precaução e para medir o texto como o leitor o conta.
 */
function bahia_subtitulo_texto($post_id) {
    $bruto = get_post_meta($post_id, 'subtitulo', true);

    if (!is_string($bruto) || $bruto === '') {
        return '';
    }

    $texto = html_entity_decode(wp_strip_all_tags($bruto), ENT_QUOTES, 'UTF-8');

    return trim(preg_replace('/\s+/u', ' ', $texto));
}

/**
 * Preenche o slot de subtítulo que já existe no template do single.
 *
 * O loop-single.php do td-composer (e o single.php do pacote mobile) já imprimem
 * <p class="td-post-sub-title"> quando td_post_theme_settings['td_subtitle'] tem valor —
 * a chave está vazia em 100% do acervo porque a redação escreve no ACF. Preencher pelo
 * filtro de metadado usa o slot nativo, com a marcação e o estilo do próprio tema, sem
 * editar plugins/ nem o tema.
 *
 * Restrito ao post da própria página: os cards de sidebar e de relacionados leem a mesma
 * chave e não podem ser afetados.
 */
function bahia_subtitulo_injeta($valor, $post_id, $meta_key, $single) {
    static $reentrante = false;

    if ($reentrante || $meta_key !== 'td_post_theme_settings' || !$single || $valor !== null) {
        return $valor;
    }
    if (!is_singular() || (int) $post_id !== (int) get_queried_object_id()) {
        return $valor;
    }

    $texto = bahia_subtitulo_texto($post_id);
    if ($texto === '') {
        return $valor;
    }

    $reentrante = true;
    $settings = get_post_meta($post_id, 'td_post_theme_settings', true);
    $reentrante = false;

    if (!is_array($settings)) {
        $settings = array();
    }

    // Subtítulo escrito no próprio campo do tagDiv tem precedência sobre o ACF.
    if (!empty($settings['td_subtitle'])) {
        return $valor;
    }

    $settings['td_subtitle'] = esc_html($texto);

    // Com $single = true o core devolve $check[0]; o array externo é só o invólucro.
    return array($settings);
}
add_filter('get_post_metadata', 'bahia_subtitulo_injeta', 10, 4);

/**
 * O subtítulo como resumo dos cards de listagem.
 *
 * A produção usa o subtítulo em TODAS as listagens — home, editorias, busca e sidebars
 * (bahia_refactor/functions.php:893 e 956, sidebar-*.php) — e só cai no corpo da matéria
 * quando o campo está vazio. O Newspaper mostra sempre o primeiro parágrafo, que é texto
 * corrido e costuma parar no meio de uma frase.
 *
 * Roda no mesmo hook do corte de 160 (bahia-limites-texto.php:192), com prioridade 20
 * para chegar depois dele: o que aquele filtro escreveu a partir do post_content fica
 * como está quando não há subtítulo, que é exatamente o comportamento de reserva pedido —
 * o card nunca sai vazio, ao contrário do desktop da produção, que imprime <p> vazio.
 *
 * Escreve no post_excerpt do objeto EM MEMÓRIA que o módulo vai renderizar. Não toca no
 * banco, não passa por get_the_excerpt e não chega a feed, REST nem meta tag.
 *
 * A guarda do post da própria página é herdada de propósito: no single, o post consultado
 * não é tocado, e é isso que mantém o Yoast lendo o post intacto quando ele precisa gerar
 * a description sozinho (caso sem subtítulo).
 */
function bahia_subtitulo_no_card($module, $post = null) {
    if (!$post instanceof WP_Post) {
        return $module;
    }
    if (!function_exists('bahia_limites_cortar') || !function_exists('bahia_limites_contexto_isento')) {
        return $module;
    }
    if (bahia_limites_contexto_isento() || bahia_limites_e_o_post_da_pagina($post->ID)) {
        return $module;
    }

    $subtitulo = bahia_subtitulo_texto($post->ID);
    if ($subtitulo === '') {
        return $module;
    }

    $limite = defined('BAHIA_LIMITE_RESUMO') ? BAHIA_LIMITE_RESUMO : 160;
    $corte  = bahia_limites_cortar($subtitulo, $limite);

    // bahia_limites_cortar() devolve null quando já cabe, e o texto já escapado quando
    // corta. O módulo imprime o post_excerpt sem escapar, então os dois ramos entregam
    // HTML pronto.
    $post->post_excerpt = ($corte === null)
        ? htmlspecialchars($subtitulo, ENT_QUOTES, 'UTF-8', false)
        : $corte;

    return $module;
}
add_filter('td_wp_booster_module_constructor', 'bahia_subtitulo_no_card', 20, 2);

/**
 * O post por trás de uma apresentação do Yoast, ou 0 quando não for um post.
 *
 * Os filtros de description do Yoast disparam também em home, arquivo, autor e páginas
 * de sistema. Só o object_type 'post' interessa.
 */
function bahia_subtitulo_post_do_yoast($presentation) {
    if (!is_object($presentation) || !isset($presentation->model)) {
        return 0;
    }

    $modelo = $presentation->model;

    if (!isset($modelo->object_type) || $modelo->object_type !== 'post') {
        return 0;
    }

    return isset($modelo->object_id) ? (int) $modelo->object_id : 0;
}

/**
 * O subtítulo como description nas meta tags.
 *
 * O tema antigo alimentava description, og:description e twitter:description com o
 * subtítulo (bahia_refactor/header.php:24-37). No Newspaper quem responde é o Yoast, e o
 * resultado medido em homolog é ruim: os templates metadesc-* estão todos vazios e apenas
 * 968 posts de 271.679 têm description escrita à mão, então em 99,6% do acervo a tag
 * <meta name="description"> simplesmente não sai, e o og:description vira o primeiro
 * parágrafo do corpo cortado no meio — que é o que o WhatsApp mostra no card do link.
 *
 * Entra como FALLBACK, nunca como sobrescrita:
 *   1. description escrita à mão no Yoast  -> mantida como está;
 *   2. subtítulo preenchido                -> vira a description;
 *   3. nenhum dos dois                     -> o Yoast segue fazendo o que já fazia.
 *
 * Devolve texto cru, sem escape: o Yoast passa por strip_all_tags() e escapa com
 * esc_attr() na saída (Abstract_Indexable_Tag_Presenter::present()). Pré-escapar aqui
 * produziria &amp;quot; na tag.
 *
 * @param array $chaves Campos do Yoast que, preenchidos à mão, têm precedência.
 */
function bahia_subtitulo_como_description($descricao, $presentation, $chaves) {
    $post_id = bahia_subtitulo_post_do_yoast($presentation);
    if (!$post_id) {
        return $descricao;
    }

    foreach ($chaves as $chave) {
        $propria = get_post_meta($post_id, $chave, true);
        if (is_string($propria) && trim($propria) !== '') {
            return $descricao;
        }
    }

    $subtitulo = bahia_subtitulo_texto($post_id);

    return $subtitulo === '' ? $descricao : $subtitulo;
}

add_filter('wpseo_metadesc', function ($descricao, $presentation = null) {
    return bahia_subtitulo_como_description($descricao, $presentation, array('_yoast_wpseo_metadesc'));
}, 10, 2);

// og e twitter caem no campo geral do Yoast quando não têm um próprio, então respeitam os
// dois: quem escreveu qualquer um dos dois à mão continua mandando.
add_filter('wpseo_opengraph_desc', function ($descricao, $presentation = null) {
    return bahia_subtitulo_como_description($descricao, $presentation, array(
        '_yoast_wpseo_opengraph-description',
        '_yoast_wpseo_metadesc',
    ));
}, 10, 2);

add_filter('wpseo_twitter_description', function ($descricao, $presentation = null) {
    return bahia_subtitulo_como_description($descricao, $presentation, array(
        '_yoast_wpseo_twitter-description',
        '_yoast_wpseo_metadesc',
    ));
}, 10, 2);

/**
 * Estilo do subtítulo no single.
 *
 * O padrão do Newspaper para .td-post-sub-title é Open Sans 16px itálico peso 300 em
 * #999 (themes/Newspaper/style.css:4316) — dimensionado para um H1 bem menor. Sob o H1
 * de 41px deste site o resultado lê como legenda de foto, não como subtítulo.
 *
 * A proporção adotada é a da produção (H1 32px / subtítulo 22px, Roboto, sem itálico,
 * #706f73), trazida para a escala do tema novo. Roboto é a fonte dos títulos do site
 * desde a rodada 7, quando o Rubik saiu — não é importação nova.
 */
add_action('wp_enqueue_scripts', function () {
    if (is_admin() || !is_singular()) {
        return;
    }

    $css = <<<CSS
.td-post-sub-title{
    font-family:var(--td_default_google_font_2,'Roboto',sans-serif);
    font-size:20px;
    font-style:normal;
    font-weight:400;
    line-height:1.35;
    color:#666;
    margin:4px 0 16px;
}
@media (max-width:767px){
    .td-post-sub-title{
        font-size:18px;
        margin:4px 0 14px;
    }
}
CSS;

    wp_register_style('bahia-subtitulo', false, array(), '1.0.0');
    wp_enqueue_style('bahia-subtitulo');
    wp_add_inline_style('bahia-subtitulo', $css);
}, 45);
