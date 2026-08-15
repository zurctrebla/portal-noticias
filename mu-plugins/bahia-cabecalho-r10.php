<?php
/**
 * Plugin Name: Bahia.ba - Cabeçalho da rodada 10
 * Description: Ajustes de cabeçalho pedidos pelo setor de qualidade na rodada 10:
 *                1. Logo centralizada numa faixa própria, abaixo da barra de menu, e
 *                   repetida na barra fixa (sticky) para acompanhar o topo junto com
 *                   o menu.
 *                2. Acima do menu fica só o banner, centralizado.
 *                3. Fundo branco no lugar da textura de mosaico e barra de menu
 *                   estendida até as bordas da tela.
 *
 *              As faixas da logo são rows do template 547414 marcadas por el_class
 *              (.bahia-logo-faixa e .bahia-logo-sticky). A âncora é a CLASSE, nunca o
 *              id .tdi_NN: o tagDiv renumera esses ids a cada salvamento de template e
 *              qualquer CSS preso a eles quebra em silêncio.
 *
 *              DIMENSÃO DA LOGO: o bloco tdb_header_logo não dimensiona a imagem. Ele
 *              emite <img width="1151" height="229"> e deixa a largura por conta do
 *              contêiner — sem CSS, a marca ocupa os ~1068px da row inteira. Por isso a
 *              largura vai aqui, em px, com height:auto para preservar a proporção
 *              (1151/229 = 5,026:1). Medir o render, não confiar no atributo.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Largura da logo na faixa estática (abaixo do menu). Altura = 260/5,026 ≈ 51,7px. */
const BAHIA_R10_LOGO_FAIXA_W = 260;

/**
 * Largura da logo na barra fixa. Altura ≈ 29,8px.
 *
 * A logo ENCOLHE no estado fixo: 260px em repouso -> 150px grudada (-42%). São duas
 * alturas empilhadas (logo + menu) e, sem encolher, a barra comeria tela demais.
 *
 * Conta da altura do cabeçalho fixo, medida:
 *     faixa da logo   42px  (logo 30 + 6 de respiro em cima e embaixo)
 *   + barra de menu   48px
 *   + linha vermelha   3px
 *   = TOTAL           93px
 *
 * Os 3px da linha valem uma nota: a row do separador tem 13px, dos quais 10 são
 * padding-bottom — espaço morto abaixo da linha. Em repouso ele separa a barra do
 * banner; grudado, só empurra o conteúdo para baixo à toa, e por isso é zerado
 * apenas dentro de .td-header-desktop-sticky-wrap.
 */
const BAHIA_R10_LOGO_STICKY_W = 150;

/** Altura do cabeçalho fixo (42+48+3). Alimenta o scroll-padding das âncoras. */
const BAHIA_R10_STICKY_H = 93;

/**
 * Cor do texto do item de menu sob o mouse.
 *
 * É o #042efc (azul da editoria Política) CLAREADO 75% em direção ao branco, pela
 * mesma técnica de bahia_hover_ed_cor_legivel: preserva o matiz e ganha luminância.
 *
 * Por que não o #042efc puro: sobre o azul da barra (#15559e) ele dá **1,01:1**.
 * Não é "pouco contraste", é ausência — as duas cores têm luminância quase idêntica
 * (0,0901 contra 0,0913) e o texto sumiria. E não há saída escurecendo: o melhor
 * que se consegue por baixo é 2,83:1, o contraste do preto puro. Só clareando.
 *
 *   #042efc puro ....... 1,01:1   reprova
 *   #c0cbfe (75% branco) 4,68:1   passa AA para texto pequeno
 *   branco (repouso) ... 7,43:1
 *
 * Cor fixa para todos os itens: navegação global não muda de cor conforme a seção,
 * mesmo critério do chip do menu mobile.
 */
const BAHIA_R10_MENU_HOVER = '#c0cbfe';

/**
 * Marcador da editoria atual: chip AZUL CLARO com texto escuro.
 *
 * Era um chip preto (o `a::after` do tema, com opacity 1 no item ativo). O preto
 * some do menu por completo aqui.
 *
 * Por que chip claro e não um azul escuro: a barra já é um azul médio-escuro
 * (#15559e). Qualquer azul MAIS ESCURO que ela contrasta menos que o próprio
 * preto, que já estava em 2,83:1 — o #042efc, por exemplo, dá 1,01:1 e sumiria.
 * A única combinação que parece azul, se destaca da barra e mantém o texto
 * legível é a inversa: fundo claro, texto escuro.
 *
 *   chip #c0cbfe contra a barra #15559e ... 4,68:1   passa AA
 *   texto #15559e sobre o chip #c0cbfe ... 4,68:1   passa AA
 *
 * O gatilho é `current-menu-item` e só ele: `tdb-cur-menu-item` aparece em
 * "Últimas Notícias" em quase toda página e NÃO acende o chip — pintar o texto
 * dele de #15559e deixaria azul sobre azul, ilegível.
 */
const BAHIA_R10_MENU_ATIVO_BG   = '#c0cbfe';
const BAHIA_R10_MENU_ATIVO_TEXT = '#15559e';

function bahia_cabecalho_r10_css() {
    $faixa  = BAHIA_R10_LOGO_FAIXA_W;
    $sticky = BAHIA_R10_LOGO_STICKY_W;
    $barra  = BAHIA_R10_STICKY_H;
    $ancora = BAHIA_R10_STICKY_H + 7;   // folga para a âncora não colar na barra
    $hover  = BAHIA_R10_MENU_HOVER;
    $at_bg  = BAHIA_R10_MENU_ATIVO_BG;
    $at_tx  = BAHIA_R10_MENU_ATIVO_TEXT;

    return <<<CSS
/* ------------------------------------------------------------------ item 1
   Faixa da logo, abaixo da barra de menu, em todas as páginas.
   O bloco já vem com align_horiz=content-horiz-center; aqui só se define o
   tamanho, que o bloco não controla. */
.bahia-logo-faixa .tdb-logo-img{
    width:{$faixa}px !important;
    height:auto !important;
    max-width:100% !important;
}
.bahia-logo-faixa .tdb-logo-a,
.bahia-logo-faixa h1,
.bahia-logo-faixa h2{
    margin:0 !important;
    justify-content:center !important;
}
/* O respiro de 48px entre o cabeçalho e o primeiro bloco da home vinha do
   margin-bottom do td_block_trending_now, que saiu junto com a faixa "EM ALTA":
   sem isto o conteúdo encosta no cabeçalho. É só na home — as páginas internas
   têm o próprio respiro, de 51px, e não devem ganhar mais.

   ELE VAI NA ÚLTIMA ROW DO CABEÇALHO, que hoje é a do banner. Enquanto a faixa da
   logo era a última, ficava nela; depois da inversão isso passou a empurrar os
   48px para DENTRO do cabeçalho, entre a logo e o menu — o espaço branco virava
   128px e a logo ficava 24px acima do centro (14 em cima, 62 embaixo). Era a
   causa da logo "mais acima que o meio", e só na home, porque a regra é .home.

   E os 48px são DIVIDIDOS, não empilhados embaixo. Inteiros no margin-bottom, o
   banner ficava com 20px acima e 68px abaixo — assimetria visível só na home,
   porque nas internas não há esse acréscimo (lá é 20/20). Metade sobe para o
   padding-top e metade fica no margin-bottom:

       acima  = 20 + 24 = 44px
       abaixo = 20 + 24 = 44px

   A altura total do cabeçalho não muda (44+90+20+24 = 178, contra 20+90+20+48),
   então o respiro de 48px antes do conteúdo continua valendo. */
body.home .bahia-header-brand{
    padding-top:44px !important;
    margin-bottom:24px !important;
}

/* Âncoras internas não podem parar debaixo da barra fixa. */
html{
    scroll-padding-top:{$ancora}px;
}

/* Enxuga a barra fixa: zera o padding morto abaixo da linha vermelha, que só
   existe para separar a barra do banner no estado de repouso. Tira 10px de 103. */
.td-header-desktop-sticky-wrap .bahia-menu-linha .td_block_separator{
    padding-bottom:0 !important;
}

/* Logo na barra fixa: menor, para a barra não engordar e não empurrar o menu. */
.bahia-logo-sticky .tdb-logo-img{
    width:{$sticky}px !important;
    height:auto !important;
    max-width:100% !important;
}
.bahia-logo-sticky .tdb-logo-a,
.bahia-logo-sticky h1,
.bahia-logo-sticky h2{
    margin:0 !important;
    justify-content:center !important;
}

/* ------------------------------------------------------------------ item 3
   Fundo branco no lugar do mosaico e barra de menu até as bordas da tela.

   O SITE CONTINUA EM td-boxed-layout. Trocar para wide mudaria a largura útil de
   todos os blocos e desfaria as rodadas 2 a 9 (respiro de 48px, largura dos cards,
   coluna de 324px dos retângulos, encaixe dos 10 itens do menu). Não é preciso:

   o azul da barra NÃO está na row — as rows são transparentes. Ele está num filho
   `.td-element-style`, position:absolute, left:-24px, width:1164px, z-index:0. É só
   esse elemento que limita a barra à caixa. Esticando ELE para 100vw, a barra vai até
   as bordas e NADA MAIS SE MOVE: menu, lupa e colunas ficam onde estavam, porque o
   elemento é absoluto e fica atrás do conteúdo. A linha vermelha #dd3333 é um
   border-top de 3px e recebe o mesmo tratamento, para não descolar da barra.

   A barra cinza do topo (clima, data e redes, #f4f4f4) usa exatamente o mesmo
   mecanismo e entra na mesma regra: era a última borda de contêiner visível em
   telas largas. Clima, data e ícones não saem do lugar — só o cinza se estende.

   Vale para as duas zonas de desktop: a normal e a fixa (sticky).

   SOBRE O 100vw E A BARRA DE ROLAGEM: `100vw` inclui a largura da barra, então o
   elemento fica ~15px mais largo que a área visível (medido: vai de -7 a 1913 numa
   janela de 1905 úteis). Isso NÃO gera rolagem horizontal porque o próprio tema põe
   `overflow-x:hidden` em `.td-theme-wrap`, que corta a sobra — conferido com a barra
   de rolagem visível: scrollWidth == clientWidth == 1905. É uma dependência real:
   se um dia essa regra do tema sair, aparece rolagem horizontal e a correção é
   limitar a largura aqui em vez de usar 100vw. */
body.td-boxed-layout{
    background-image:none !important;
    background-color:#ffffff !important;
}

.bahia-topo-row > .td-element-style,
.bahia-menu-row > .td-element-style,
.bahia-menu-linha > .td-element-style{
    left:calc(50% - 50vw) !important;
    width:100vw !important;
    max-width:none !important;
}
/* a linha vermelha é o border-top do filho do separador */
.bahia-menu-linha .td_block_separator > *{
    position:relative;
    left:calc(50% - 50vw);
    width:100vw !important;
    max-width:none !important;
}

/* Fundo branco ÚNICO, sem a sombra que separava os ambientes.

   O td-boxed-layout desenha três sombras para destacar a caixa do fundo. Com o
   fundo branco elas viraram uma divisão cinza visível entre a área de conteúdo e
   as laterais — justamente o que não se quer. São regras do tema, com seletores
   estáveis (sem .tdi_NN), então dá para anular exatamente estas três:

     .td-header-desktop-wrap .tdc_zone   box-shadow: 0 -13px 10px 3px rgba(0,0,0,.12)
     .td-main-content-wrap               box-shadow: 0   0px 10px 3px rgba(0,0,0,.12)
     .td-footer-wrap .tdc_zone           box-shadow: 0  13px 10px 3px rgba(0,0,0,.12)

   A sombra do dropdown da busca (.tdb-drop-down-search-inner) NÃO entra aqui: é
   sombra de componente, não de contêiner, e continua valendo. */
.td-header-desktop-wrap .tdc_zone,
.td-main-content-wrap,
.td-footer-wrap .tdc_zone{
    box-shadow:none !important;
}

/* ------------------------------------------------------------------ item 4
   Hover dos ITENS DO MENU superior: texto azul no lugar do preto.

   O QUE ACONTECE HOJE, medido: não é a cor do texto que muda. Todo item tem um
   `a::after` preto ocupando a área inteira (68x48), e o que alterna é a OPACIDADE
   — 0 em repouso, 1 sob o mouse. O texto continua branco; o que se vê é um chip
   preto aparecendo atrás dele. Daí a impressão de "o texto ficou preto".

   Esse MESMO chip marca o item ativo (rodada 7). Por isso o hover aqui é tratado
   só para item NÃO ativo: o chip do ativo fica intacto, e sob o mouse o chip não
   aparece — quem muda é a cor do texto.

   Vale para as duas zonas por causa de .bahia-menu-row. Não usar .tdi_62/.tdi_81:
   nesta rodada esses ids já viraram .tdi_60/.tdi_88 sozinhos.

   Não confundir com o verde #008d7f dos SUBMENUS (rodada 8) nem com o hover de
   título de card por editoria (rodadas 6 e 7): nenhum dos dois é tocado aqui. */
/* EM REPOUSO, a editoria atual: chip azul claro com texto escuro, no lugar do
   bloco preto. Vem ANTES das regras de hover de propósito — as duas usam
   !important e mesma especificidade, então quem vier depois vence, e o hover
   tem de vencer. */
.bahia-menu-row .tdb-menu > li.current-menu-item > a::after{
    background-color:{$at_bg} !important;
}
.bahia-menu-row .tdb-menu > li.current-menu-item > a,
.bahia-menu-row .tdb-menu > li.current-menu-item > a .tdb-menu-item-text{
    color:{$at_tx} !important;
}

/* O chip some sob o mouse em TODOS os itens, inclusive no ativo.
   Preservá-lo no item ativo foi uma tentativa de manter a marca de "você está
   aqui" durante o hover, mas o efeito prático era o oposto do pedido: dentro de
   uma editoria, o item daquela editoria — o primeiro em que se passa o mouse —
   continuava exibindo o retângulo preto. O texto até ficava azul por baixo, só
   que quem domina a leitura é o bloco preto, não a cor da letra.
   A marca do item ativo continua intacta EM REPOUSO; ela só cede enquanto o
   ponteiro está sobre o item, e volta assim que ele sai. */
.bahia-menu-row .tdb-menu > li:hover > a::after,
.bahia-menu-row .tdb-menu > li.tdb-hover > a::after,
.bahia-menu-row .tdb-menu > li > a:focus-visible::after{
    opacity:0 !important;
}
/* A cor do texto acompanha, também em todos os itens. */
.bahia-menu-row .tdb-menu > li:hover > a,
.bahia-menu-row .tdb-menu > li:hover > a .tdb-menu-item-text,
.bahia-menu-row .tdb-menu > li.tdb-hover > a,
.bahia-menu-row .tdb-menu > li.tdb-hover > a .tdb-menu-item-text,
.bahia-menu-row .tdb-menu > li > a:focus-visible,
.bahia-menu-row .tdb-menu > li > a:focus-visible .tdb-menu-item-text{
    color:{$hover} !important;
}
/* o foco por teclado precisa continuar visível por si só, sem depender da cor */
.bahia-menu-row .tdb-menu > li > a:focus-visible{
    outline:2px solid {$hover};
    outline-offset:-2px;
}
CSS;
}

function bahia_cabecalho_r10_enqueue() {
    if (is_admin()) {
        return;
    }
    wp_register_style('bahia-cabecalho-r10', false, array(), '1.0.0');
    wp_enqueue_style('bahia-cabecalho-r10');
    wp_add_inline_style('bahia-cabecalho-r10', bahia_cabecalho_r10_css());
}
// prioridade 45: depois de bahia-header-menu-fit (40), que também mexe no cabeçalho
add_action('wp_enqueue_scripts', 'bahia_cabecalho_r10_enqueue', 45);
