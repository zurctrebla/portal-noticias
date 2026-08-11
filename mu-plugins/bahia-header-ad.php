<?php
/**
 * Plugin Name: Bahia.ba - Marca + publicidade no header
 * Description: Shortcode [bahia_header_ad] para o banner 728x90 do header (AdRotate) e o
 *              CSS da linha logo + publicidade do tdb_template 547414.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_HEADER_AD_VER', '1.0.0');

/**
 * Grupo de reserva do leaderboard do header.
 *
 * O valor NORMAL vem de bahia_pub_grupo_topo() (bahia-publicidade.php), que escolhe o grupo
 * pelo contexto da página como o tema legado fazia: 1 na home, 14 em municípios, 12 nas
 * internas. Até a rodada 8 este arquivo cravava o grupo 3 — "Home - Leader Board 2" — em
 * TODAS as páginas, ou seja, entregava inventário de home nas internas.
 *
 * Este 12 só vale se o bahia-publicidade.php estiver ausente: é o grupo de internas, que é o
 * contexto da maioria das páginas do site. Antes o fallback era inventário de home.
 */
define('BAHIA_HEADER_AD_GROUP', 12);

/**
 * Banner do header.
 *
 * Existe porque o bloco de texto do TagDiv não serve: o tdm_block_inline_text não chama
 * do_shortcode em lugar nenhum, então nem [adrotate] nem um shortcode próprio rodariam
 * dentro dele. Colocado direto na coluna do zone, o do_shortcode do próprio template
 * executa — mesmo caminho já usado por [bahia_clubes_sidebar] na home.
 *
 * O td_block_ad_box que estava aqui antes também não servia: ele lê os "ad spots" do
 * painel do TagDiv, não o AdRotate, e estava com spot_id vazio.
 */
function bahia_header_ad_shortcode() {
    if (!shortcode_exists('adrotate')) {
        return '';
    }

    $grupo = function_exists('bahia_pub_grupo_topo')
        ? bahia_pub_grupo_topo()
        : BAHIA_HEADER_AD_GROUP;

    $ad = do_shortcode('[adrotate group="' . (int) $grupo . '"]');

    // Sem criativo elegível o AdRotate devolve só um comentário HTML. Não envolver em
    // div nenhuma nesse caso, senão sobra uma caixa vazia empurrando o layout.
    if (trim(strip_tags($ad)) === '' && strpos($ad, '<img') === false) {
        return '';
    }

    // data-grupo deixa auditável, no HTML servido, qual inventário foi entregue em cada
    // contexto — é o que se confere quando o comercial questiona a entrega.
    return '<div class="bahia-header-ad" data-grupo="' . (int) $grupo . '">' . $ad . '</div>';
}
add_shortcode('bahia_header_ad', 'bahia_header_ad_shortcode');

function bahia_header_ad_css() {
    return <<<CSS
/* Linha marca + publicidade do header
   Conta: row de 1068px (o site e td-boxed-layout, o max-width de 1240 do desenho
   original nunca chegava a valer) - 40 de padding = 1028 uteis.
   256 (logo) + 24 (gap) + 728 (banner) = 1008, folga de 20px.

   --bahia-logo-w e a fonte unica da largura: vale para a imagem e para a coluna que a
   contem. Sem largura na coluna o flex a encolhe ate o conteudo, e o max-width:100%
   herdado de `img` arrasta a imagem junto. */
.bahia-header-brand {
    --bahia-logo-w: 256px;
    max-width: 100%;
    margin: 0 auto;
    min-height: 130px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
    background: #fff;
}
/* .wpb_row junto no seletor porque o padding lateral do .td-pb-row vence um seletor de
   classe so e zerava o padding horizontal */
.bahia-header-brand.wpb_row {
    padding: 20px;
}
.bahia-header-brand .tdb-logo-a img {
    width: var(--bahia-logo-w);
    height: auto;
    display: block;
    /* impede o max-width:100% do tema de encolher junto com a coluna */
    max-width: none;
}
.bahia-header-ad {
    flex: 0 0 728px;
    max-width: 728px;
    min-width: 0;
    text-align: right;
}
.bahia-header-ad img {
    display: block;
    max-width: 100%;
    height: auto;
    margin-left: auto;
}

/* O TagDiv injeta um <style> como primeiro filho da row. Sem escode-lo ele conta como
   item de flex e consome um gap inteiro antes do logo -- e e por isso que os seletores
   abaixo sao :first-of-type/:last-of-type e nao :first-child, que nunca casaria com a
   coluna do logo. */
.bahia-header-brand > style {
    display: none;
}
/* O .td-pb-row usa ::before/::after como clearfix. Em container flex eles viram itens
   de flex de largura zero, e o de antes consome um gap inteiro -- era o que empurrava a
   linha 24px para a direita e fazia o banner estourar a borda do conteudo. */
.bahia-header-brand.wpb_row::before,
.bahia-header-brand.wpb_row::after {
    display: none;
}
/* As colunas sao do grid do TagDiv e vem com padding lateral proprio. A largura em
   porcentagem foi removida do tdc_css delas no proprio template, senao brigaria com o
   !important gerado pelo TagDiv. */
.bahia-header-brand > .vc_column {
    width: auto;
    padding-left: 0;
    padding-right: 0;
}
.bahia-header-brand > .vc_column:first-of-type {
    flex: 0 0 var(--bahia-logo-w);
}
.bahia-header-brand > .vc_column:last-of-type {
    flex: 0 0 auto;
    margin-left: auto;
}
/* o bloco de logo centraliza por padrao (align_horiz do shortcode) */
.bahia-header-brand .tdb_header_logo .tdb-block-inner {
    justify-content: flex-start;
}

@media (max-width: 1080px) {
    .bahia-header-brand { --bahia-logo-w: 220px; flex-direction: column; gap: 16px; min-height: 0; }
    .bahia-header-brand.wpb_row { padding: 16px; }
    .bahia-header-ad { flex: 0 0 auto; text-align: center; }
    .bahia-header-brand .tdb-logo-a img { margin: 0 auto; }
    .bahia-header-ad img { margin-left: auto; margin-right: auto; }
    /* empilhado, a coluna do logo deixa de ser faixa fixa na horizontal */
    .bahia-header-brand > .vc_column:first-of-type { flex: 0 0 auto; }
    .bahia-header-brand > .vc_column:last-of-type { margin-left: 0; }
}
/* Nao ha bloco para 767px: abaixo desse ponto o tema esconde .td-header-desktop-wrap
   inteiro e renderiza o header mobile, que e outra chave do template (tdc_header_mobile).
   Esta row nem existe la. */
CSS;
}

function bahia_header_ad_enqueue() {
    if (is_admin()) {
        return;
    }
    wp_register_style('bahia-header-ad', false, array(), BAHIA_HEADER_AD_VER);
    wp_enqueue_style('bahia-header-ad');
    wp_add_inline_style('bahia-header-ad', bahia_header_ad_css());
}
add_action('wp_enqueue_scripts', 'bahia_header_ad_enqueue', 30);
