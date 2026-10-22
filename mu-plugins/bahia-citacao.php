<?php
/**
 * Plugin Name: Bahia.ba - Bloco de citação flexível (KAN-32)
 * Description: O botão "Bloco de citação" do editor gera um <blockquote>, e o tema
 *              Newspaper (style.css, `.tagdiv-type blockquote p`) impunha a TODA citação
 *              a cor do tema, caixa-alta, itálico, centralização e fonte de 32px. Nada
 *              disso dava para desfazer no editor: o repórter escrevia em minúsculas e o
 *              site publicava em MAIÚSCULAS, na cor fixa.
 *
 *              Aqui o bloco deixa de impor aparência ao texto. Fica só a identidade do
 *              bloco (recuo com barra azul do bahia.ba à esquerda e corpo um pouco maior)
 *              e o resto vem do que o repórter fizer no editor: cor do texto, negrito,
 *              itálico, alinhamento e maiúsculas só se digitadas assim. A formatação do
 *              editor sai como estilo inline e por isso vence estas regras.
 *
 *              Só vale para a citação comum (`blockquote` sem classe). Os posts
 *              incorporados do X, Instagram e TikTok também são <blockquote>, mas com
 *              classe (twitter-tweet, instagram-media...), e ficam intocados.
 * Version: 1.1.0
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
.tagdiv-type blockquote:not([class]){
    margin:30px 0;
    padding:2px 0 2px 20px;
    border-left:4px solid #15559e;
    font-style:normal;
    text-align:left;
    color:#222222;
}
.tagdiv-type blockquote:not([class]) p{
    font-family:inherit;
    font-size:18px;
    line-height:1.6;
    font-weight:inherit;
    text-transform:none;
    color:inherit;
}
.tagdiv-type blockquote:not([class]) p:last-child{margin-bottom:0;}
@media (max-width:767px){
    .tagdiv-type blockquote:not([class]){margin:22px 0;padding-left:14px;}
    .tagdiv-type blockquote:not([class]) p{font-size:17px;}
}
CSS;
    wp_register_style('bahia-citacao', false, array(), '1.0.0');
    wp_enqueue_style('bahia-citacao');
    wp_add_inline_style('bahia-citacao', $css);
}, 31);

/* -------------------------------------------------------------------------
 *  FONTE E TAMANHO NO EDITOR
 *
 *  A cor o editor clássico já oferece (botão "A" da 2ª linha). Fonte e tamanho são
 *  controles nativos do TinyMCE que o WordPress deixa desligados: entram aqui na 2ª
 *  linha, depois do menu "Formatos" do tagDiv (td_mce_buttons_2, prioridade 10).
 *  O resultado sai como estilo inline no texto, e por isso vence o CSS acima —
 *  dentro ou fora de uma citação.
 *
 *  Só fontes que o leitor de fato recebe: Verdana (a do corpo do texto), Roboto (a
 *  dos títulos, carregada pelo tema), e Arial e Georgia, que todo aparelho tem.
 * ------------------------------------------------------------------------- */
add_filter('mce_buttons_2', function ($buttons) {
    $pos = array_search('styleselect', $buttons, true);
    $pos = ($pos === false) ? 0 : $pos + 1;
    array_splice($buttons, $pos, 0, array('fontselect', 'fontsizeselect'));
    return $buttons;
}, 20);

add_filter('tiny_mce_before_init', function ($init) {
    $init['font_formats'] = 'Padrão do texto (Verdana)=Verdana,Geneva,sans-serif;'
        . 'Roboto=Roboto,Arial,sans-serif;'
        . 'Arial=Arial,Helvetica,sans-serif;'
        . 'Georgia=Georgia,serif';
    $init['fontsize_formats'] = '14px 15px 16px 18px 20px 22px 24px 28px 32px';
    return $init;
}, 20);
