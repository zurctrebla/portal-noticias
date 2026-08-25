<?php
/**
 * Plugin Name: Bahia.ba - Azul do site nos controles de UI
 * Description: Troca o verde-água do demo Magazine PRO (#008d7f) pelo azul institucional
 *              do bahia.ba (#15559E) em dois controles — e só nesses dois, de propósito:
 *                - o botão flutuante "voltar ao topo" (.td-scroll-up);
 *                - o botão "VER MAIS NOTÍCIAS" que substitui a paginação numerada.
 *              Os demais botões do site ficam como estão (pedido explícito da rodada 2).
 *
 *              O #15559E é o azul da barra de menu e dos botões do tema anterior
 *              (bahia_refactor, assets/css/base.css:205 e 1142), o mesmo aplicado na
 *              barra do menu principal nesta rodada.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Azul institucional do bahia.ba. */
if (!defined('BAHIA_AZUL')) {
    define('BAHIA_AZUL', '#15559e');
}
/** Tom mais escuro para hover/active. */
if (!defined('BAHIA_AZUL_ESCURO')) {
    define('BAHIA_AZUL_ESCURO', '#0f4079');
}

add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    $azul   = BAHIA_AZUL;
    $escuro = BAHIA_AZUL_ESCURO;

    $css = <<<CSS
/* voltar ao topo — o demo trazia #008d7f */
.td-scroll-up,
.td-scroll-up:hover{background-color:{$azul} !important;}

/* Botão "VER MAIS NOTÍCIAS". Existe em dois sabores, porque as listagens do site
   são renderizadas por dois caminhos diferentes:
     .td-load-more-wrap a   -> load more nativo do tagDiv (home, busca, autor,
                               categoria, tag, data — blocos tdb_loop)
     .bahia-load-more-btn   -> botão do mu-plugin bahia-scroll-infinito, usado nos
                               archives de editoria, que são renderizados em PHP
   Só estes. As setas .td-next-prev-wrap são pintadas por bahia-editoria-tags.php, com
   :has() a partir do card — NÃO pelos itens 3 e 4, que tratam título e foto no hover.
   (Esta linha afirmava o contrário até a rodada 9; media-se cinza #dcdcdc nas sete.) */
.td-load-more-wrap a,
.bahia-load-more-btn{
    background-color:{$azul} !important;
    border-color:{$azul} !important;
    color:#fff !important;
    text-transform:uppercase;
}
.td-load-more-wrap a:hover,
.td-load-more-wrap a:focus,
.bahia-load-more-btn:hover,
.bahia-load-more-btn:focus{
    background-color:{$escuro} !important;
    border-color:{$escuro} !important;
    color:#fff !important;
}

/* Textura do demo na barra de editorias.
   O Magazine PRO põe uma camada .td-element-style-before com bghd.jpg a 20% de
   opacidade por cima do azul da linha do menu, deixando o fundo sujo. A camada
   existe duas vezes: na barra normal e na cópia sticky — por isso o escopo é o
   .td-header-template-wrap, que envolve as duas, e não os ids tdi_NN, que o
   TagDiv renumera a cada edição do template. Fora do header a mesma classe é
   usada em fundos legítimos de seção, então não pode ser global.
   Três classes de propósito: a regra do TagDiv (.tdi_NN_rand_style > .td-element
   -style-before) também usa !important e tem especificidade (0,2,0); como ela é
   impressa inline no corpo, DEPOIS do nosso <head>, um empate a faria vencer por
   ordem de origem. */
.td-header-template-wrap .td-element-style > .td-element-style-before{
    background-image:none !important;
}

/* Rótulo "EM ALTA" — vinha no verde-água do demo (#008d7f). Preto, para casar
   com "MAIS LIDAS". */
.td-trending-now-title{
    background-color:#000 !important;
    color:#fff !important;
}

/* ------------------------------------------------------------------
   BUSCA — o verde-água do demo (#008d7f) passa ao azul do site.
   Cobre os dois lugares em que a busca aparece:
     .tdb-head-search-*  -> lupa do cabeçalho (e a cópia sticky)
     .tdb-search-*       -> formulário da página de resultados
   Escopo por classe estrutural, nunca por .tdi_NN — o TagDiv renumera.
   O MENU fica de fora de propósito: aguarda autorização.
   ------------------------------------------------------------------ */
.tdb-head-search-form-btn:hover,
.tdb-search-form-btn:hover{
    background-color:{$azul} !important;
}
/* "Ver todos os resultados" sob o campo do cabeçalho. */
.tdb-head-search-form .result-msg a:hover,
.tdb-aj-search .result-msg a:hover{
    color:{$azul} !important;
}
/* Item destacado pelas SETAS DO TECLADO no dropdown da busca. Não é hover, então o
   item 3 (cor da editoria no hover) não o cobre e o verde ainda aparecia. */
.tdb-aj-search .tdb-aj-cur-element .entry-title a{
    color:{$azul} !important;
}
/* Paginação da página de resultados: página atual e hover. */
.search-results .page-nav .current,
.search-results .page-nav a:hover,
.search-results .td-load-more-wrap a:hover,
.search-results .td-next-prev-wrap a:hover{
    background-color:{$azul} !important;
    border-color:{$azul} !important;
    color:#fff !important;
}
/* Nome do autor no hover, na listagem de resultados. */
.search-results .td-post-author-name:hover a{
    color:{$azul} !important;
}

/* NÃO se mexe no título do card no hover: ali vale a cor da EDITORIA (item 3 da
   rodada 6). O verde do demo naquele seletor já perde por !important/especificidade
   e nunca chega à tela — sobrescrevê-lo com azul desfaria a rodada 6. */

/* ------------------------------------------------------------------
   MENU — o verde-água do demo nos itens de SUBMENU (autorizado na rodada 8).

   São quatro regras do demo, duas por bloco de menu (tdi_62 no cabeçalho normal e
   tdi_81 na cópia sticky): uma pinta o texto, outra o ícone SVG da seta. Todas
   exigem `.tdb-menu ul .tdb-normal-menu…`, ou seja, item dentro de um <ul> de
   SUBMENU. O menu de editorias é raso hoje, então NENHUMA delas chega à tela —
   isto é CSS morto.

   Trocar mesmo assim é o ponto: no dia em que alguém criar um dropdown no menu, o
   verde-água do demo apareceria sozinho, sem aviso e sem ninguém associar a causa.

   Escopo estrutural, sem os ids .tdi_NN, que o TagDiv renumera a cada edição do
   template — por isso as duas cópias do menu ficam cobertas pela mesma regra. O
   !important é necessário: sem o .tdi_NN a especificidade cai de (0,5,1) para
   (0,4,1) e a regra do demo venceria.
   ------------------------------------------------------------------ */
.tdb-menu ul .tdb-normal-menu.current-menu-item>a,
.tdb-menu ul .tdb-normal-menu.current-menu-ancestor>a,
.tdb-menu ul .tdb-normal-menu.current-category-ancestor>a,
.tdb-menu ul .tdb-normal-menu.tdb-hover>a,
.tdb-menu ul .tdb-normal-menu:hover>a,
.tdb-menu-items-dropdown .td-pulldown-filter-list li:hover>a{
    color:{$azul} !important;
}
.tdb-menu ul .tdb-normal-menu.current-menu-item>a .tdb-sub-menu-icon-svg svg,
.tdb-menu ul .tdb-normal-menu.current-menu-item>a .tdb-sub-menu-icon-svg svg *,
.tdb-menu ul .tdb-normal-menu.current-menu-ancestor>a .tdb-sub-menu-icon-svg svg,
.tdb-menu ul .tdb-normal-menu.current-menu-ancestor>a .tdb-sub-menu-icon-svg svg *,
.tdb-menu ul .tdb-normal-menu.current-category-ancestor>a .tdb-sub-menu-icon-svg svg,
.tdb-menu ul .tdb-normal-menu.current-category-ancestor>a .tdb-sub-menu-icon-svg svg *,
.tdb-menu ul .tdb-normal-menu.tdb-hover>a .tdb-sub-menu-icon-svg svg,
.tdb-menu ul .tdb-normal-menu.tdb-hover>a .tdb-sub-menu-icon-svg svg *,
.tdb-menu ul .tdb-normal-menu:hover>a .tdb-sub-menu-icon-svg svg,
.tdb-menu ul .tdb-normal-menu:hover>a .tdb-sub-menu-icon-svg svg *,
.tdb-menu-items-dropdown .td-pulldown-filter-list li:hover>a .tdb-sub-menu-icon-svg svg,
.tdb-menu-items-dropdown .td-pulldown-filter-list li:hover>a .tdb-sub-menu-icon-svg svg *{
    fill:{$azul} !important;
}

/* ------------------------------------------------------------------
   MENU MOBILE — item ativo (rodada 9)

   O demo pintava o item corrente com `--td_mobile_text_active_color: #00a392`, via
   `.td-mobile-content .current-menu-item > a`. Era o único resquício visível do demo
   no celular.

   Por que CHIP e não troca de cor do texto: o painel do menu é TRANSLÚCIDO — a página
   aparece atrás dele, e isso é estado final (capturas em 3s e 7s idênticas), não
   animação. Amostrando o pixel renderizado atrás dos 10 itens, o azul da marca como
   TEXTO dá 1,07–1,69:1: invisível. O mesmo azul como FUNDO, com texto branco por cima,
   dá 7,27:1 — e é o que faz a cor da marca de fato aparecer.

   `display:inline-block` é necessário: o <a> do menu ocupa a linha inteira, e sem isso
   o fundo viraria uma barra de ponta a ponta em vez de um chip ajustado ao rótulo.

   O contorno de 1px existe porque o chip é elemento GRÁFICO sobre fundo translúcido
   variável (WCAG 1.4.11 pede 3:1 do chip contra o que estiver atrás). O fundo atrás
   muda conforme a foto da matéria que ficou embaixo, então o contorno claro garante a
   borda do chip mesmo onde o azul se aproxima do fundo.
   ------------------------------------------------------------------ */
.td-mobile-content .current-menu-item>a,
.td-mobile-content .current-menu-ancestor>a,
.td-mobile-content .current-category-ancestor>a{
    color:#fff !important;
    background-color:{$azul};
    display:inline-block;
    padding:2px 10px;
    border-radius:3px;
    border:1px solid rgba(255,255,255,.55);
}

/* ------------------------------------------------------------------
   RODAPÉ — o degradê vinha como PNG de 271 KB.

   O template do rodapé pinta o fundo com uma camada .td-element-style-before
   carregando footer-bg-azul.png. Medido: 1366x768, RGB, 271 KB — e é a ÚNICA
   imagem que o navegador busca de imediato em toda página do site (confirmado
   no network do Chrome: 4ª requisição, antes da maior parte do JS).

   O arquivo não tem textura nenhuma. Decodificado pixel a pixel:
     - variação vertical ZERO em todas as colunas amostradas
     - variação horizontal linear e contínua, 139 cores na amostra
     - extremos: x=0 -> rgb(7,30,251)   x=1365 -> rgb(87,3,244)
   É um linear-gradient horizontal puro, gravado como bitmap. Uma linha de CSS
   entrega o mesmo resultado sem os 271 KB.

   O escopo é .td-footer-template-wrap, o wrapper estrutural, e não os ids
   tdi_NN — o TagDiv os renumera a cada edição do template. Conferido que dentro
   do rodapé as ÚNICAS camadas com background-image:url() são as duas do próprio
   footer-bg-azul (tdi_154 e tdi_162); não há fundo legítimo de seção ali para
   ser atropelado. Fora do rodapé a classe é usada em fundos que devem ficar,
   então a regra não pode ser global — mesma razão da textura do cabeçalho acima.

   Especificidade: a regra do TagDiv (.tdi_NN_rand_style > .td-element-style-before)
   é (0,2,0) e também usa !important, e é impressa inline no corpo, DEPOIS do
   nosso <head>. Um empate a faria vencer por ordem de origem. Os três seletores
   de classe abaixo dão (0,3,0) e resolvem sem depender da ordem.
   ------------------------------------------------------------------ */
.td-footer-template-wrap .td-element-style > .td-element-style-before{
    background-image:linear-gradient(90deg,#071efb 0%,#5703f4 100%) !important;
}
CSS;

    wp_register_style('bahia-cores-ui', false, array(), '1.0.0');
    wp_enqueue_style('bahia-cores-ui');
    wp_add_inline_style('bahia-cores-ui', $css);
}, 45);
