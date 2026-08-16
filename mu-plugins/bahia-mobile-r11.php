<?php
/**
 * Plugin Name: Bahia.ba - Ajustes de celular da rodada 11
 * Description: Três pedidos, todos restritos ao celular (<=767px), sem tocar no
 *              desktop, que está encerrado e validado:
 *                1. Fundo branco no cabeçalho mobile, com a logo colorida como está.
 *                2. Cards de EC Bahia / EC Vitória fora da HOME.
 *                3. Bloco "Mundo" entre "Brasil" e o vídeo do YouTube.
 *
 *              O CORTE É SEMPRE 767px, que é onde o tema troca de cabeçalho:
 *              abaixo disso ele esconde .td-header-desktop-wrap e renderiza
 *              .td-header-mobile-wrap. Usar o mesmo ponto evita estado híbrido.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Azul da marca — era o fundo da barra, passa a ser a cor dos ícones. */
const BAHIA_R11_AZUL = '#15559e';

function bahia_mobile_r11_css() {
    $azul = BAHIA_R11_AZUL;

    return <<<CSS
@media (max-width: 767px){

/* ------------------------------------------------------------------ item 1
   Fundo branco atrás da logo.

   O cabeçalho mobile é UMA barra só (391x55): hambúrguer, logo e lupa, com o
   menu atrás do hambúrguer. Não existe "barra de menu mobile" separada, então
   não há risco de o branco parecer um recorte — ou a barra toda é azul, ou é
   toda branca. A linha vermelha #dd3333 continua logo abaixo, separando o
   cabeçalho do conteúdo.

   O azul não está no wrapper: está num filho .td-element-style, o mesmo
   mecanismo do desktop. É ele que muda de cor.

   A LOGO NÃO É TOCADA: segue o anexo 547365, colorida, sem filtro. Sobre
   branco a leitura melhora — era azul sobre azul.

   OS ÍCONES PRECISAM MUDAR JUNTO: hambúrguer e lupa são brancos (fill
   #ffffff), o que sobre branco dá 1,00:1 — sumiriam. Passam ao azul da marca,
   que dá 7,43:1 sobre branco e mantém a identidade: a cor migra do fundo para
   os ícones em vez de desaparecer do cabeçalho.

   SÃO DUAS BARRAS, NÃO UMA. O celular tem a barra normal
   (.td-header-mobile-wrap) e a FIXA, que aparece ao rolar
   (.td-header-mobile-sticky-wrap) — zonas separadas do template, com classes
   distintas. Pintar só a primeira deixava a barra voltar a ser azul, com
   ícones brancos, assim que a pessoa rolasse a página. Por isso o seletor
   [class*="td-header-mobile"], que alcança as duas. */
[class*="td-header-mobile"] .td-element-style{
    background-color:#ffffff !important;
}
[class*="td-header-mobile"] .tdb-mobile-menu-button,
[class*="td-header-mobile"] .tdb-mobile-menu-button .tdb-mobile-menu-icon,
[class*="td-header-mobile"] .tdb_mobile_search a,
[class*="td-header-mobile"] .tdb_mobile_search i{
    color:{$azul} !important;
}
[class*="td-header-mobile"] .tdb-mobile-menu-button svg,
[class*="td-header-mobile"] .tdb-mobile-menu-button svg *,
[class*="td-header-mobile"] .tdb_mobile_search svg,
[class*="td-header-mobile"] .tdb_mobile_search svg *{
    fill:{$azul} !important;
    color:{$azul} !important;
}

/* ------------------------------------------------------------------ item 2
   Cards de EC Bahia / EC Vitória fora do celular — SÓ NA HOME.

   Escopo confirmado com o responsável: /esporte/ e /brasileirao-2026/ ficam
   como estão. Em /brasileirao-2026/ os cards são o conteúdo principal, no topo
   da página; removê-los deixaria a página vazia no celular.

   Esconde-se o CONTÊINER (.bahia-cl-sidebar), não os cards, por dois motivos:
   ele carrega o margin-bottom de 48px — escondendo só os cards sobraria a
   margem — e na home o botão "Tabela completa" está DENTRO dele, então sai
   junto, que é o desejado. Em /esporte/ o botão é outro (.bahia-esp-tab) e
   pertence à classificação; como aqui nada é escondido lá, ele nem é afetado.

   display:none e não visibility/opacity: não pode sobrar caixa nem espaço. */
body.home .bahia-cl-sidebar{
    display:none !important;
}

/* ------------------------------------------------------------------ item 3
   "Mundo" entre "Brasil" e o vídeo do YouTube.

   Ordem no celular antes desta regra:
       ...  ROW3 [Municípios · BRASIL] · ROW4 [YOUTUBE] · ROW5 [Últimas · MUNDO]

   O obstáculo: os três estão em rows DIFERENTES, e `order` só reordena irmãos
   do mesmo contêiner flex. Reordenar no banco resolveria, mas mexeria no
   desktop — por isso não foi feito.

   A saída: .tdc_zone vira flex column e a ROW5 recebe display:contents, o que
   dissolve a caixa dela e faz suas duas colunas subirem ao nível das rows.
   Aí `order` alcança as três peças. É seguro aqui porque foi medido: a ROW5
   não tem fundo, padding nem margem própria, então não há nada para perder ao
   dissolvê-la. (O is_sticky="yes" da coluna do Mundo é de desktop e não vale
   aqui dentro da media query.)

   As classes .bahia-row-mundo / .bahia-col-mundo / .bahia-col-ultimas foram
   acrescentadas ao conteúdo da home APENAS como âncora — nenhuma ordem foi
   alterada no banco. Os ids .tdi_NN não servem: renumeram sozinhos a cada
   salvamento de template (aconteceu três vezes na rodada 10). A row do vídeo é
   alcançada por :has(.bahia-yt), que não precisa de âncora nova.

   Ordem resultante:  Municípios · BRASIL · MUNDO · YOUTUBE · Últimas Notícias */
.td-main-content-wrap .tdc-content-wrap .tdc_zone{
    display:flex !important;
    flex-direction:column !important;
}
/* padrão: tudo mantém a sequência do DOM (itens de mesma ordem não se movem) */
.td-main-content-wrap .tdc-content-wrap .tdc_zone > .tdc-row{
    order:20;
}
/* a row do Mundo deixa de ser caixa: as colunas viram itens do zone */
.tdc-row:has(> .bahia-row-mundo),
.bahia-row-mundo{
    display:contents !important;
}
.bahia-col-mundo{
    order:50 !important;
}
.td-main-content-wrap .tdc-content-wrap .tdc_zone > .tdc-row:has(.bahia-yt){
    order:60 !important;
}
.bahia-col-ultimas{
    order:70 !important;
}
/* o respiro de 48px entre blocos, padronizado na rodada 5, vale também para as
   duas colunas que passaram a ser itens do zone */
.bahia-col-mundo,
.bahia-col-ultimas{
    margin-bottom:48px;
}
}
CSS;
}

function bahia_mobile_r11_enqueue() {
    if (is_admin()) {
        return;
    }
    wp_register_style('bahia-mobile-r11', false, array(), '1.0.0');
    wp_enqueue_style('bahia-mobile-r11');
    wp_add_inline_style('bahia-mobile-r11', bahia_mobile_r11_css());
}
// prioridade 50: depois de bahia-cabecalho-r10 (45), para vencer no empate
add_action('wp_enqueue_scripts', 'bahia_mobile_r11_enqueue', 50);
