<?php
/**
 * Plugin Name: Bahia.ba - Marca + publicidade no header
 * Description: Shortcode [bahia_header_ad] para o banner do header (AdRotate) e o
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
/* Linha de publicidade do header — SO o banner, centralizado (rodada 10).
   Ate a rodada 9 esta row tinha logo a esquerda e banner a direita. A logo saiu
   daqui: passou para a faixa centralizada abaixo do menu (bahia-cabecalho-r10.php).

   Conta atualizada: row de 1068px (o site e td-boxed-layout, o max-width de 1240 do
   desenho original nunca chegava a valer) - 40 de padding = 1028 uteis, agora
   inteiros para o banner. Antes eram 256 (logo) + 24 (gap) + 728 = 1008.

   Consequencia para o comercial: com 1028 uteis, um criativo de 970x90 passa a caber
   NATIVO — ver PUBLICIDADE-slots.md 3.1, que registrava que os grupos 1 e 12 estao
   declarados como 970x90 mas so recebem pecas de 728x90. O teto abaixo subiu de 728
   para 970 para nao continuar sendo ele o limitador. Nada estica: um 728 continua
   sendo servido a 728, porque a imagem respeita a largura natural. */
.bahia-header-brand {
    max-width: 100%;
    margin: 0 auto;
    min-height: 130px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #fff;
}
/* .wpb_row junto no seletor porque o padding lateral do .td-pb-row vence um seletor de
   classe so e zerava o padding horizontal */
.bahia-header-brand.wpb_row {
    padding: 20px;
}
.bahia-header-ad {
    flex: 0 1 auto;
    max-width: 970px;
    min-width: 0;
    text-align: center;
}
.bahia-header-ad img {
    display: block;
    max-width: 100%;
    height: auto;
    margin-left: auto;
    margin-right: auto;
}

/* O TagDiv injeta um <style> como primeiro filho da row. Sem escode-lo ele conta como
   item de flex e desloca a centralizacao do banner. (Ate a rodada 9 ele consumia um
   gap inteiro antes da logo; o gap saiu, mas o <style> continua sendo um item de flex
   e por isso a regra permanece.) */
.bahia-header-brand > style {
    display: none;
}
/* O .td-pb-row usa ::before/::after como clearfix. Em container flex eles viram itens
   de flex de largura zero e desequilibram o `justify-content: center`. */
.bahia-header-brand.wpb_row::before,
.bahia-header-brand.wpb_row::after {
    display: none;
}
/* As colunas sao do grid do TagDiv e vem com padding lateral proprio. A largura em
   porcentagem foi removida do tdc_css delas no proprio template, senao brigaria com o
   !important gerado pelo TagDiv. */
/* Agora ha UMA coluna so, a do anuncio. As regras de duas colunas
   (:first-of-type com a largura da logo, :last-of-type com margin-left:auto
   empurrando para a direita) sairam junto com a logo — se tivessem ficado, a de
   margin-left:auto continuaria jogando o banner para a direita. */
.bahia-header-brand > .vc_column {
    width: auto;
    padding-left: 0;
    padding-right: 0;
    flex: 0 1 auto;
    margin: 0 auto;
}

@media (max-width: 1080px) {
    /* Com uma coluna so nao ha mais o que empilhar; resta reduzir o respiro
       vertical, que era o outro efeito deste bloco. */
    .bahia-header-brand { min-height: 0; }
    .bahia-header-brand.wpb_row { padding: 16px; }
    .bahia-header-ad { flex: 0 1 auto; text-align: center; }
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
