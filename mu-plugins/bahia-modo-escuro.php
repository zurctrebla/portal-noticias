<?php
/**
 * Plugin Name: Bahia.ba - Modo escuro (KAN-15)
 * Description: Interruptor claro/escuro no cabeçalho (desktop e celular), ao lado da lupa.
 *
 *              COMO FUNCIONA, E POR QUE ASSIM
 *
 *              - A escolha mora no navegador (localStorage). O HTML é o MESMO para todos —
 *                é o que permite seguir servindo a página pelo fastcgi_cache. Nada de cookie
 *                nem de PHP decidindo a classe (é o que o td_dark_mode do tagDiv faz, e com
 *                cache de página ele entregaria a uma pessoa o modo escolhido por outra).
 *
 *              - Sem piscar: um script mínimo no começo do <head> lê a escolha e põe a classe
 *                `bahia-dark` no <html> ANTES da primeira pintura. Todo o CSS escuro é
 *                escopado nessa classe; sem ela, o site fica exatamente como era.
 *
 *              - Faixas do tagDiv: o fundo de cada faixa (linha do cabeçalho, seção da home...)
 *                vem de regras embutidas na página, `.tdi_NN_rand_style{background-color:#fff}`,
 *                com NN diferente em cada página. O buffer de saída (bahia-html-saida.php) lê
 *                essas regras e gera a versão escura só para as faixas de fundo CLARO; as
 *                azuis e pretas ficam como estão.
 *
 *              - Padrão: claro. Cores de editoria (tags), fotos e anúncios não mudam.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_DM_VER', '1.0.0');
define('BAHIA_DM_CHAVE', 'bahia-tema'); // chave do localStorage: 'escuro' | 'claro'

/** Cores do modo escuro (fundo da barra do navegador incluído). */
function bahia_dm_cores() {
    return array(
        'fundo'     => '#121417', // página
        'sup'       => '#1a1e23', // superfícies: caixas, cards com fundo, dropdowns
        'sup2'      => '#232931', // superfície elevada / hover
        'borda'     => '#2f363f',
        'texto'     => '#e7e9ec', // títulos e texto forte (15,6:1 sobre o fundo)
        'texto2'    => '#c3c9d1', // corpo do texto (11,4:1)
        'suave'     => '#9aa3ae', // datas, meta, legendas (7,1:1)
        'link'      => '#8ab8f2', // links no texto (8,6:1)
        'claro_tc'  => '#ffffff', // theme-color do modo claro
    );
}

/* -------------------------------------------------------------------------
 *  1) <head>: aplica a classe antes da pintura. Prioridade 0 = antes de qualquer CSS.
 * ------------------------------------------------------------------------- */
add_action('wp_head', function () {
    if (is_admin()) {
        return;
    }
    $c = bahia_dm_cores();
    $chave = BAHIA_DM_CHAVE;
    $escuro = $c['fundo'];
    // Mínimo e sem dependências: roda antes de jQuery e de qualquer folha de estilo.
    echo "<script id=\"bahia-dm-boot\">(function(){try{if(localStorage.getItem('{$chave}')==='escuro'){document.documentElement.classList.add('bahia-dark');"
        . "var m=document.querySelector('meta[name=theme-color]');if(!m){m=document.createElement('meta');m.name='theme-color';document.head.appendChild(m);}m.content='{$escuro}';}}catch(e){}})();</script>\n";
}, 0);

/* -------------------------------------------------------------------------
 *  2) Botão no cabeçalho: dentro dos blocos da lupa (desktop, sticky e celular).
 * ------------------------------------------------------------------------- */
function bahia_dm_botao() {
    // Dois ícones no mesmo botão; o CSS mostra a lua no claro e o sol no escuro.
    $lua = '<svg class="bahia-dm-lua" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><path fill="currentColor" d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>';
    $sol = '<svg class="bahia-dm-sol" viewBox="0 0 24 24" width="18" height="18" aria-hidden="true" focusable="false"><circle cx="12" cy="12" r="4.5" fill="currentColor"/><g stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M12 1.5v3M12 19.5v3M1.5 12h3M19.5 12h3M4.6 4.6l2.1 2.1M17.3 17.3l2.1 2.1M4.6 19.4l2.1-2.1M17.3 6.7l2.1-2.1"/></g></svg>';
    return '<button type="button" class="bahia-dm-btn" aria-pressed="false" aria-label="Ativar modo escuro" title="Modo escuro">'
        . $lua . $sol . '</button>';
}

add_filter('bahia_hs_html', 'bahia_dm_saida', 25);

function bahia_dm_saida($html) {
    if (!is_string($html) || strpos($html, '<html') === false) {
        return $html;
    }

    // 2a) botão: primeiro filho de cada bloco de busca do cabeçalho.
    $html = preg_replace(
        '#(<div class="td_block_wrap (?:tdb_header_search|tdb_mobile_search)\b[^"]*"[^>]*>)#',
        '$1' . bahia_dm_botao(),
        $html
    );

    // 2b) faixas do tagDiv com fundo claro: versão escura gerada a partir da própria página.
    if (preg_match_all('~\.(tdi_\d+_rand_style)\{background-color:\s*(#[0-9a-fA-F]{3,6})\s*!important~', $html, $m, PREG_SET_ORDER)) {
        $c = bahia_dm_cores();
        $regras = '';
        $vistos = array();
        foreach ($m as $r) {
            if (isset($vistos[$r[1]]) || bahia_dm_luminancia($r[2]) < 0.75) {
                continue;
            }
            $vistos[$r[1]] = true;
            $regras .= 'html.bahia-dark .' . $r[1] . '{background-color:' . $c['fundo'] . ' !important}';
        }
        if ($regras !== '') {
            $html = preg_replace('#</head>#', '<style id="bahia-dm-faixas">' . $regras . '</style></head>', $html, 1);
        }
    }
    return $html;
}

/** Luminância relativa (0 = preto, 1 = branco) de uma cor #rgb/#rrggbb. */
function bahia_dm_luminancia($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (strlen($hex) !== 6) {
        return 0;
    }
    $l = array();
    foreach (str_split($hex, 2) as $p) {
        $v = hexdec($p) / 255;
        $l[] = $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $l[0] + 0.7152 * $l[1] + 0.0722 * $l[2];
}

/* -------------------------------------------------------------------------
 *  3) CSS e JS do interruptor.
 * ------------------------------------------------------------------------- */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    wp_register_style('bahia-modo-escuro', false, array(), BAHIA_DM_VER);
    wp_enqueue_style('bahia-modo-escuro');
    wp_add_inline_style('bahia-modo-escuro', bahia_dm_css());

    wp_register_script('bahia-modo-escuro', false, array(), BAHIA_DM_VER, true);
    wp_enqueue_script('bahia-modo-escuro');
    wp_add_inline_script('bahia-modo-escuro', bahia_dm_js());
}, 99);

function bahia_dm_js() {
    $c = bahia_dm_cores();
    $chave = BAHIA_DM_CHAVE;
    return <<<JS
(function () {
    'use strict';
    var html = document.documentElement;
    function meta(cor) {
        var m = document.querySelector('meta[name=theme-color]');
        if (!m) { m = document.createElement('meta'); m.name = 'theme-color'; document.head.appendChild(m); }
        m.content = cor;
    }
    function sincroniza() {
        var escuro = html.classList.contains('bahia-dark');
        var bts = document.querySelectorAll('.bahia-dm-btn');
        for (var i = 0; i < bts.length; i++) {
            bts[i].setAttribute('aria-pressed', escuro ? 'true' : 'false');
            bts[i].setAttribute('aria-label', escuro ? 'Desativar modo escuro' : 'Ativar modo escuro');
            bts[i].setAttribute('title', escuro ? 'Modo claro' : 'Modo escuro');
        }
    }
    document.addEventListener('click', function (e) {
        var b = e.target.closest ? e.target.closest('.bahia-dm-btn') : null;
        if (!b) { return; }
        e.preventDefault();
        e.stopPropagation();
        var escuro = !html.classList.contains('bahia-dark');
        html.classList.toggle('bahia-dark', escuro);
        try { localStorage.setItem('{$chave}', escuro ? 'escuro' : 'claro'); } catch (err) {}
        meta(escuro ? '{$c['fundo']}' : '{$c['claro_tc']}');
        sincroniza();
    }, true);
    // Outra aba mudou a escolha: acompanha.
    window.addEventListener('storage', function (e) {
        if (e.key !== '{$chave}') { return; }
        html.classList.toggle('bahia-dark', e.newValue === 'escuro');
        sincroniza();
    });
    if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', sincroniza); } else { sincroniza(); }
})();
JS;
}

function bahia_dm_css() {
    $c = bahia_dm_cores();
    extract($c, EXTR_PREFIX_ALL, 'c');
    $D = 'html.bahia-dark body';

    return <<<CSS
/* ---------- botão (vale nos dois modos) ---------- */
.tdb_header_search,.tdb_mobile_search{position:relative;}
.bahia-dm-btn{position:absolute;top:50%;right:100%;transform:translateY(-50%);display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;margin:0 2px 0 0;padding:0;border:0;border-radius:50%;background:transparent;color:#ffffff;cursor:pointer;z-index:5;-webkit-appearance:none;appearance:none;}
.bahia-dm-btn:hover{background:rgba(255,255,255,.14);}
.bahia-dm-btn:focus-visible{outline:2px solid #ffffff;outline-offset:2px;}
.tdb_mobile_search .bahia-dm-btn{color:#15559e;}
.tdb_mobile_search .bahia-dm-btn:hover{background:rgba(21,85,158,.10);}
.tdb_mobile_search .bahia-dm-btn:focus-visible{outline-color:#15559e;}
.bahia-dm-btn svg{display:block;}
.bahia-dm-sol{display:none !important;}
html.bahia-dark .bahia-dm-lua{display:none !important;}
html.bahia-dark .bahia-dm-sol{display:block !important;}
html.bahia-dark .tdb_mobile_search .bahia-dm-btn{color:{$c_texto};}

/* ---------- base ---------- */
html.bahia-dark{color-scheme:dark;background-color:{$c_fundo};}
{$D}{background-color:{$c_fundo} !important;color:{$c_texto2};}
{$D} .td-main-content-wrap,{$D} .td-container-wrap,{$D} .td-category-header,{$D} .td-page-header,{$D} .tdc-content-wrap,{$D} #td-outer-wrap,{$D} .td-theme-wrap,{$D} .td-header-template-wrap,{$D} .td-header-desktop-wrap,{$D} .td-header-mobile-wrap{background-color:{$c_fundo} !important;}

/* cabeçalho: faixa do logo e cabeçalho do celular (este tem fundo claro por regra própria, fora do padrão rand_style) */
{$D} .td-header-mobile-wrap .tdc-row,{$D} .td-header-mobile-sticky-wrap,{$D} .td-header-mobile-sticky-wrap .tdc-row,{$D} .td-header-mobile-wrap .td-element-style,{$D} .td-header-mobile-sticky-wrap .td-element-style,{$D} .bahia-header-brand,{$D} .bahia-topo-row{background-color:{$c_fundo} !important;}
/* o tagDiv põe uma camada ::after branca sobre a faixa do cabeçalho do celular */
{$D} .td-header-mobile-wrap .td-element-style::after,{$D} .td-header-mobile-sticky-wrap .td-element-style::after{background-color:{$c_fundo} !important;}
/* ícones das redes no topo (no rodapé eles já são brancos sobre o azul e ficam como estão) */
{$D} .td-header-template-wrap .tdm-social-item i,{$D} .td-header-template-wrap .tdm-social-item{color:{$c_texto2} !important;}
{$D} .td-header-template-wrap .tdm-social-item svg,{$D} .td-header-template-wrap .tdm-social-item svg *{fill:{$c_texto2} !important;}
/* item ativo do menu: a pílula lilás (#c0cbfe, ::after do bahia-cores-ui.php) vira um realce translúcido,
   com o texto branco. (tdb-cur-menu-item NÃO marca o item atual: o tagDiv põe essa classe no 1º item.) */
{$D} .bahia-menu-row .tdb-menu > li.current-menu-item > a::after,{$D} .bahia-menu-row .tdb-menu > li.current-menu-ancestor > a::after{background-color:rgba(255,255,255,.18) !important;}
{$D} .bahia-menu-row .tdb-menu > li.current-menu-item > a,{$D} .bahia-menu-row .tdb-menu > li.current-menu-item > a .tdb-menu-item-text,{$D} .bahia-menu-row .tdb-menu > li.current-menu-ancestor > a,{$D} .bahia-menu-row .tdb-menu > li.current-menu-ancestor > a .tdb-menu-item-text{color:#ffffff !important;}
/* barrinha do topo: clima e data */
{$D} .tdb_header_weather,{$D} .tdb_header_weather *,{$D} .tdb_header_date,{$D} .tdb_header_date *{color:{$c_texto2} !important;}
{$D} .tdb_header_weather svg,{$D} .tdb_header_weather svg *{fill:{$c_texto2} !important;}
{$D} .tdb_header_logo .tdb-logo-img{filter:brightness(0) invert(1);}
{$D} .tdb_mobile_menu .tdb-mobile-menu-icon svg,{$D} .tdb_mobile_menu .tdb-mobile-menu-icon svg *,{$D} .tdb_mobile_search .tdb-header-search-button-mob svg,{$D} .tdb_mobile_search svg *{fill:{$c_texto} !important;}
{$D} .tdb_mobile_menu .tdb-mobile-menu-icon,{$D} .tdb_mobile_search .tdb-header-search-button-mob i{color:{$c_texto} !important;}

/* submenu, dropdown da busca */
{$D} .tdb-menu .sub-menu,{$D} .tdb-menu .tdb-mega-menu-inner,{$D} .tdb-drop-down-search-inner,{$D} .tdb-search-form,{$D} .tdb-search-form:before,{$D} .tdb-search-form-inner,{$D} .tdb-aj-search-results,{$D} .tdb-aj-search .result-msg{background-color:{$c_sup} !important;border-color:{$c_borda} !important;}
{$D} .tdb-menu .sub-menu a,{$D} .tdb-menu .sub-menu .tdb-menu-item-text{color:{$c_texto} !important;}
{$D} .tdb-head-search-form-input,{$D} .tdb-head-search-placeholder{color:{$c_texto} !important;}
{$D} .tdb-search-form-inner:after{border-color:{$c_borda} !important;}

/* menu lateral do celular */
{$D} .td-menu-background:before,{$D} .td-search-background:before{background:{$c_fundo} !important;}
{$D} #td-mobile-nav,{$D} .td-mobile-container,{$D} .td-search-wrap-mob{background-color:{$c_fundo} !important;}
{$D} #td-mobile-nav a,{$D} .td-mobile-content a,{$D} .td-mobile-content .td-icon-menu-right,{$D} .td-search-wrap-mob .td-drop-down-search,{$D} .td-search-wrap-mob *{color:{$c_texto} !important;}
{$D} .td-mobile-content li{border-color:{$c_borda} !important;}

/* ---------- títulos, meta, texto ---------- */
{$D} .td_block_wrap .td-module-title a,{$D} .td_block_wrap .entry-title a,{$D} .td-module-title a,{$D} .entry-title,{$D} .entry-title a,{$D} h1,{$D} h2,{$D} h3,{$D} h4,{$D} h5,{$D} h6,{$D} .tdb-title-text,{$D} .td-block-title,{$D} .td_block_wrap .td-block-title > *,{$D} .td_block_wrap .td-block-title a,{$D} .block-title span,{$D} .td-pulldown-filter-display-option{color:{$c_texto} !important;}
{$D} .td-post-author-name,{$D} .td-post-author-name a,{$D} .td-author-by,{$D} .td-editor-date .td-post-author-name a{color:{$c_texto2} !important;}
{$D} .td-post-date,{$D} .td-module-date,{$D} .entry-date,{$D} .td-author-line,{$D} .td-excerpt,{$D} .td-post-sub-title,{$D} .td-module-comments a,{$D} .td-author-date,{$D} .entry-crumbs,{$D} .entry-crumbs a,{$D} .td-bred-no-url-last,{$D} .tdb-breadcrumbs,{$D} .tdb-breadcrumbs a,{$D} .tdb-entry-crumb,{$D} .td-crumb-container a{color:{$c_suave} !important;}
{$D} .bahia-coautores-e{color:{$c_suave} !important;}

/* corpo da matéria (o <article> tem fundo branco próprio) */
{$D} .td-ss-main-content article,{$D} article.post,{$D} .td-post-content,{$D} .td-post-header{background-color:transparent !important;}
{$D} .td-post-content,{$D} .td-post-content p,{$D} .td-post-content li,{$D} .tagdiv-type,{$D} .tagdiv-type p,{$D} .tagdiv-type li,{$D} .td-page-content,{$D} .td-page-content p{color:{$c_texto2} !important;}
{$D} .td-post-content a,{$D} .tagdiv-type a,{$D} .td-page-content a{color:{$c_link} !important;}
{$D} .td-post-content strong,{$D} .td-post-content b,{$D} .tagdiv-type strong{color:{$c_texto} !important;}
{$D} .wp-caption-text,{$D} .wp-caption-dd,{$D} figcaption,{$D} .td-post-content .wp-caption-text{color:{$c_suave} !important;}
/* citação comum (bahia-citacao.php): texto claro; o que o repórter coloriu no editor continua vencendo por ser estilo inline */
{$D} .tagdiv-type blockquote:not([class]){color:{$c_texto} !important;}
{$D} .tagdiv-type blockquote:not([class]) p{color:inherit !important;}
{$D} .td-post-sharing-top,{$D} .td-post-source-tags,{$D} .td-tags a,{$D} .td-post-next-prev{border-color:{$c_borda} !important;}
{$D} .td-tags a,{$D} .td-post-small-box a{background-color:{$c_sup} !important;color:{$c_texto2} !important;border-color:{$c_borda} !important;}
{$D} .td-post-next-prev-content span{color:{$c_suave} !important;}
{$D} .td-post-next-prev-content a{color:{$c_texto} !important;}
{$D} hr,{$D} .td-post-content hr{border-color:{$c_borda} !important;background-color:{$c_borda} !important;}

/* caixa de autor (uma ou várias, bahia-coautores.php) */
{$D} .author-box-wrap{background-color:{$c_sup} !important;border-color:{$c_borda} !important;}
{$D} .author-box-wrap .td-author-name a{color:{$c_texto} !important;}
{$D} .author-box-wrap .td-author-description,{$D} .author-box-wrap .td-author-url a{color:{$c_texto2} !important;}

/* ---------- caixas e blocos próprios ---------- */
{$D} .bahia-ml-list,{$D} .bahia-cl-box,{$D} .td_block_wrap .td-block-title-wrap,{$D} .td-load-more-wrap{background-color:transparent;}
{$D} .bahia-cl-box,{$D} .bahia-ml-list{background-color:{$c_sup} !important;border-color:{$c_borda} !important;}
{$D} .bahia-cl-box *,{$D} .bahia-ml-list *{border-color:{$c_borda} !important;}
{$D} .bahia-ml-list a,{$D} .bahia-cl-box a,{$D} .bahia-cl-box{color:{$c_texto} !important;}
{$D} .td_block_wrap .td-block-title,{$D} .td_block_template_8 .td-block-title{border-color:{$c_borda} !important;}
{$D} .td_block_template_8 .td-block-title > *:before,{$D} .td_block_template_8 .td-block-title > *:after{background-color:{$c_borda} !important;}
{$D} .td-module-container:before{border-color:{$c_borda} !important;}
{$D} .td_module_wrap .td-module-meta-info{border-color:{$c_borda} !important;}

/* setas de paginação dos blocos e "load more" */
{$D} .td-next-prev-wrap a{background-color:{$c_sup} !important;}
{$D} .bahia-si-nomore{color:{$c_suave} !important;}
{$D} .page-nav a,{$D} .page-nav span{background-color:{$c_sup} !important;border-color:{$c_borda} !important;color:{$c_texto2} !important;}
{$D} .page-nav .current{background-color:#15559e !important;color:#ffffff !important;}

/* sidebar, widgets, formulários */
{$D} .td-ss-main-sidebar,{$D} .widget{color:{$c_texto2};}
{$D} .widget a{color:{$c_texto} !important;}
{$D} input[type=text],{$D} input[type=email],{$D} input[type=search],{$D} input[type=url],{$D} input[type=tel],{$D} input[type=number],{$D} input[type=password],{$D} textarea,{$D} select{background-color:{$c_sup} !important;color:{$c_texto} !important;border-color:{$c_borda} !important;}
{$D} ::placeholder{color:{$c_suave} !important;}

/* tabelas (classificação do Brasileirão e conteúdo) */
{$D} table,{$D} th,{$D} td{border-color:{$c_borda} !important;}
{$D} table th{background-color:{$c_sup2} !important;color:{$c_texto} !important;}
{$D} table td{color:{$c_texto2};}
{$D} table tr:nth-child(even) td{background-color:{$c_sup} !important;}
{$D} table tr:nth-child(odd) td{background-color:{$c_fundo} !important;}

/* página do Brasileirão (bahia-esporte-tabela / futebol): linhas de Bahia e Vitória, jogos, legenda.
   As cores dos clubes clareiam no escuro (#0a58ca e #c8102e dariam 2,9 e 3,1:1). */
{$D} .bahia-bra-tabela tr.bahia,{$D} .bahia-bra-tabela tr.vitoria,{$D} .bahia-bra-tabela tr.bahia td,{$D} .bahia-bra-tabela tr.vitoria td,{$D} .bahia-bra-tabela tbody tr:hover td{background-color:{$c_sup2} !important;}
{$D} .bahia-bra-tabela tr.bahia .bahia-bra-time{color:#86b6ff !important;}
{$D} .bahia-bra-tabela tr.vitoria .bahia-bra-time{color:#ff8593 !important;}
{$D} .bahia-bra-legenda,{$D} .bahia-bra-jogo-data,{$D} .bahia-bra-jogo-local,{$D} .bahia-bra-jogo-x,{$D} .bahia-bra-updated,{$D} .bahia-bra-load{color:{$c_suave} !important;}
{$D} .bahia-bra-jogo{background-color:{$c_sup} !important;border-color:{$c_borda} !important;}
{$D} .bahia-bra-jogo-sigla,{$D} .bahia-bra-jogo-placar,{$D} .bahia-bra-jogo-gol,{$D} .bahia-bra-jogo-nome{color:{$c_texto} !important;}

/* busca, 404, sem resultados */
{$D} .tdm-descr,{$D} .tdm-title{color:{$c_texto2} !important;}
{$D} .td-search-header,{$D} .tdb-search-header{color:{$c_texto} !important;}
{$D} .no-results h2,{$D} .td-404-title,{$D} .td-404-sub-title{color:{$c_texto} !important;border-color:{$c_borda} !important;}

/* Quem Somos */
{$D} .qs-intro,{$D} .qs-contact{color:{$c_texto2} !important;}
{$D} .qs-equipe .qs-title,{$D} .qs-name{color:{$c_texto} !important;}
{$D} .qs-role{border-color:{$c_borda} !important;color:{$c_link} !important;}
{$D} .qs-cargo,{$D} .qs-equipe .qs-sub{color:{$c_suave} !important;}
{$D} .qs-avatar{background-color:{$c_sup2} !important;}
{$D} .qs-member-link:hover .qs-name{color:{$c_link} !important;}

/* aviso de cookies (Pure GDPR Cookie Settings): painel escuro; os botões mantêm as cores deles */
{$D} .pd-gdpr-panel-setting{background:{$c_sup} !important;border-top:1px solid {$c_borda} !important;}
{$D} .pd-gdpr-panel-setting p,{$D} .pd-gdpr-panel-setting .pd-gdpr-row{color:{$c_texto} !important;}
{$D} .pd-gdpr-panel-setting p a{color:{$c_link} !important;}
CSS;
}
