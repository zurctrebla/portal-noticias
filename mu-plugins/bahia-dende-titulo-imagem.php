<?php
/**
 * Plugin Name: Bahia.ba - Dendê e Poder: título do archive como imagem
 * Description: No archive da editoria Dendê e Poder, troca o texto do <h1> pela arte da marca.
 *              Só ali: as demais editorias seguem com o título em texto.
 *
 *              Age no buffer de saída único (`bahia_hs_html`, de bahia-html-saida.php) em vez
 *              de filtrar `get_the_archive_title`, porque o markup do título vem montado pelo
 *              PHP do td-composer (`<h1 class="entry-title td-page-title"><span>…</span></h1>`)
 *              e não passa pelos filtros de título do WordPress.
 *
 *              O <h1> permanece no HTML, com o nome da editoria no `alt` da imagem: o texto
 *              continua existindo para leitor de tela e para o Google, só deixa de ser
 *              desenhado como texto.
 *
 * @author bahia.ba / Claude Code
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Arquivo na raiz do wp-content, como as fotos da equipe da Quem Somos. */
const BAHIA_DTI_ARQUIVO = 'dende-e-poder-titulo.png';
const BAHIA_DTI_LARGURA = 667;
const BAHIA_DTI_ALTURA  = 667;

add_filter('bahia_hs_html', 'bahia_dti_troca_titulo');
function bahia_dti_troca_titulo($html) {
    if (!is_post_type_archive('dende_poder')) {
        return $html;
    }
    if (!file_exists(WP_CONTENT_DIR . '/' . BAHIA_DTI_ARQUIVO)) {
        return $html;   // arte ausente: deixa o título em texto, sem buraco
    }

    // Casa o <h1> do título do archive e só ele. O `s` cobre a quebra de linha que o
    // template gera entre a tag e o <span>.
    $padrao = '#(<h1[^>]*class="[^"]*td-page-title[^"]*"[^>]*>)(.*?)(</h1>)#s';

    return preg_replace_callback($padrao, function ($m) {
        $texto = trim(wp_strip_all_tags($m[2]));
        if ($texto === '') {
            return $m[0];
        }
        $img = sprintf(
            '<img class="bahia-dende-titulo" src="%s" alt="%s" width="%d" height="%d" decoding="async" />',
            esc_url(content_url(BAHIA_DTI_ARQUIVO)),
            esc_attr($texto),
            BAHIA_DTI_LARGURA,
            BAHIA_DTI_ALTURA
        );
        return $m[1] . $img . $m[3];
    }, $html, 1);
}

add_action('wp_enqueue_scripts', 'bahia_dti_css', 20);
function bahia_dti_css() {
    if (!is_post_type_archive('dende_poder')) {
        return;
    }
    // A arte é quadrada (667x667); no título ela precisa caber sem empurrar a lista.
    $css = '
    .td-page-title .bahia-dende-titulo{
        display:block;
        width:auto;
        max-width:100%;
        height:auto;
        max-height:110px;
    }
    @media (max-width:767px){
        .td-page-title .bahia-dende-titulo{ max-height:80px; }
    }';
    wp_register_style('bahia-dende-titulo', false);
    wp_enqueue_style('bahia-dende-titulo');
    wp_add_inline_style('bahia-dende-titulo', $css);
}
