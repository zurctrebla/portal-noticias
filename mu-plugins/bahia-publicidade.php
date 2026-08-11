<?php
/**
 * Plugin Name: Bahia.ba - Slots de publicidade (AdRotate)
 * Description: Transporte dos espaços publicitários do tema legado `bahia_refactor` (o que
 *              roda em produção) para o Newspaper. Reproduz as POSIÇÕES e, principalmente, a
 *              SEPARAÇÃO POR CONTEXTO que o legado fazia e que se perdeu na migração.
 *
 *              Por que isto existe: o Newspaper renderizava UM único slot, com o grupo 3
 *              cravado em bahia-header-ad.php, em TODAS as páginas. O grupo 3 chama-se
 *              "Home - Leader Board 2" — inventário de home sendo entregue em internas e em
 *              municípios. No legado o topo NUNCA foi um grupo só:
 *
 *                  themes/bahia_refactor/header.php:200-207
 *                  if (is_home())                                 -> grupo 1  (Home)
 *                  elseif (get_query_var('post_type')=='municipios') -> grupo 14 (Municípios)
 *                  else                                            -> grupo 12 (Internas)
 *
 *              E a sidebar das internas tinha dois retângulos 300x250:
 *
 *                  themes/bahia_refactor/sidebar.php:18  -> grupo 8 (topo da sidebar)
 *                  themes/bahia_refactor/sidebar.php:89  -> grupo 9 (fim da sidebar)
 *
 *              Escopo desta rodada: só os 7 grupos com inventário ativo (1, 3, 5, 8, 9, 12,
 *              14). Os outros 11 estão zerados e renderizariam caixa vazia.
 *
 *              GRUPOS QUE FICAM SEM PLACEMENT, e por quê:
 *              - grupo 3 ("Home - Leader Board 2", 728x90): no legado a única chamada está
 *                COMENTADA (bahia_social/index.php:242, `<//?php`), e em bahia_refactor não
 *                existe chamada nenhuma. Transporte fiel = não renderizar. É o grupo que o
 *                header servia até aqui; ele sai do ar por ser inventário sem posição
 *                contratada, e a decisão comercial sobre reaproveitá-lo é do usuário.
 *              - grupo 5 ("Home - Retângulo", 300x250): não tem chamada em lugar nenhum do
 *                legado. Os arquivos ad-long.php / ad-small.php / ad-mobile.php, que seriam
 *                os candidatos naturais, estão VAZIOS (0 bytes) desde o primeiro commit. A
 *                home do Newspaper tampouco tem sidebar. Sem posição de origem, não se
 *                inventa uma.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_PUB_VER', '1.0.0');

/** Retângulo do topo da sidebar das internas (legado: sidebar.php:18). */
define('BAHIA_PUB_GRUPO_SIDEBAR_TOPO', 8);
/** Retângulo do fim da sidebar das internas (legado: sidebar.php:89). */
define('BAHIA_PUB_GRUPO_SIDEBAR_FIM', 9);

/* -------------------------------------------------------------------------
 * Contexto
 * ---------------------------------------------------------------------- */

/**
 * O legado testava `get_query_var('post_type') == 'municipios'`, que é verdadeiro tanto no
 * archive quanto no single do CPT. Aqui os três casos ficam explícitos porque o Newspaper
 * chega nesses contextos por caminhos diferentes (archive.php do tema, single.php do plugin).
 */
function bahia_pub_contexto_municipios() {
    if (is_singular('municipios') || is_post_type_archive('municipios')) {
        return true;
    }
    $pt = get_query_var('post_type');
    if (is_array($pt)) {
        $pt = reset($pt);
    }
    return $pt === 'municipios';
}

/**
 * Grupo do leaderboard do topo, conforme o contexto — o que o legado fazia no header.php.
 *
 * `is_front_page()` e não `is_home()`: aqui a home é uma PÁGINA estática (547432), então
 * `is_home()` é falso nela. No legado a home era o índice de posts e `is_home()` servia.
 */
function bahia_pub_grupo_topo() {
    if (is_front_page()) {
        return 1;  // Home - Super leader board
    }
    if (bahia_pub_contexto_municipios()) {
        return 14; // Leaderboard Municipios
    }
    return 12;     // Internas - Leaderboard
}

/* -------------------------------------------------------------------------
 * Render
 * ---------------------------------------------------------------------- */

/**
 * Devolve o HTML de um grupo do AdRotate, ou string vazia.
 *
 * Sem criativo elegível o AdRotate devolve só um comentário HTML. Devolver '' nesse caso é
 * o que impede uma caixa vazia de empurrar o layout — mesma guarda já usada no header.
 */
function bahia_pub_grupo_html($grupo) {
    if (!shortcode_exists('adrotate')) {
        return '';
    }
    $ad = do_shortcode('[adrotate group="' . (int) $grupo . '"]');
    if (trim(strip_tags($ad)) === '' && strpos($ad, '<img') === false) {
        return '';
    }
    return $ad;
}

/**
 * Retângulo 300x250 embrulhado, para os pontos de sidebar.
 *
 * `$pos` só entra como classe modificadora — serve para o CSS e para depurar no HTML qual
 * dos dois pontos rendeu o quê.
 */
function bahia_pub_retangulo($grupo, $pos) {
    $ad = bahia_pub_grupo_html($grupo);
    if ($ad === '') {
        return '';
    }
    return '<div class="bahia-pub-retangulo bahia-pub-retangulo--' . esc_attr($pos)
         . '" data-grupo="' . (int) $grupo . '">' . $ad . '</div>';
}

/**
 * Guarda contra render duplo do mesmo ponto na mesma requisição.
 *
 * Existe porque os dois caminhos de sidebar do Newspaper são diferentes e, em tese, podem
 * coexistir: o single/página do plugin passa por `tdc_sidebar`, e o archive do tema chama
 * `dynamic_sidebar('td-default')` direto. Hoje eles não se cruzam (os nomes de sidebar são
 * distintos), mas um ajuste futuro de template não pode virar dois banners empilhados.
 */
function bahia_pub_ja_rendeu($chave) {
    static $vistos = array();
    if (isset($vistos[$chave])) {
        return true;
    }
    $vistos[$chave] = true;
    return false;
}

function bahia_pub_echo_sidebar_topo() {
    if (bahia_pub_ja_rendeu('sidebar_topo')) {
        return;
    }
    echo bahia_pub_retangulo(BAHIA_PUB_GRUPO_SIDEBAR_TOPO, 'topo');
}

function bahia_pub_echo_sidebar_fim() {
    if (bahia_pub_ja_rendeu('sidebar_fim')) {
        return;
    }
    echo bahia_pub_retangulo(BAHIA_PUB_GRUPO_SIDEBAR_FIM, 'fim');
}

/* -------------------------------------------------------------------------
 * Ancoragem nos dois caminhos de sidebar do Newspaper
 * ---------------------------------------------------------------------- */

/**
 * Caminho 1 — single e página: `td-composer/legacy/Newspaper/single.php` chama
 * `get_sidebar()`, que carrega `themes/Newspaper/sidebar.php`, cujo corpo inteiro é
 * `do_action('tdc_sidebar')`. As duas prioridades extremas põem os retângulos antes e depois
 * do que o td-composer renderiza (ele se registra na prioridade padrão, 10).
 */
add_action('tdc_sidebar', 'bahia_pub_echo_sidebar_topo', 1);
add_action('tdc_sidebar', 'bahia_pub_echo_sidebar_fim', 9999);

/**
 * Caminho 2 — archive de editoria: `themes/Newspaper/archive.php:48` chama
 * `dynamic_sidebar('td-default')` direto, sem passar por `get_sidebar()`.
 *
 * `dynamic_sidebar_before` / `_after` disparam mesmo com a sidebar VAZIA — é o ramo de saída
 * antecipada do core — e hoje ela está vazia, que é justamente o caso a cobrir. O teste do
 * índice evita vazar os banners para qualquer outra área de widget.
 */
function bahia_pub_e_sidebar_do_tema($index) {
    return $index === 'td-default';
}

add_action('dynamic_sidebar_before', function ($index) {
    if (bahia_pub_e_sidebar_do_tema($index)) {
        bahia_pub_echo_sidebar_topo();
    }
}, 1);

add_action('dynamic_sidebar_after', function ($index) {
    if (bahia_pub_e_sidebar_do_tema($index)) {
        bahia_pub_echo_sidebar_fim();
    }
}, 9999);

/* -------------------------------------------------------------------------
 * CSS
 * ---------------------------------------------------------------------- */

function bahia_pub_css() {
    return <<<CSS
/* Retângulos 300x250 da sidebar (bahia-publicidade.php)
   Conta: .td-main-sidebar tem 372px com 24px de padding de cada lado -> 324 úteis.
   O criativo de 300 cabe com 24px de folga; o max-width:100% é rede de segurança para
   criativo fora de medida, que passa a encolher em vez de estourar a coluna. */
.bahia-pub-retangulo {
    margin: 0 0 24px;
    text-align: center;
    line-height: 0;
}
.bahia-pub-retangulo--fim {
    margin: 24px 0 0;
}
.bahia-pub-retangulo img {
    display: inline-block;
    max-width: 100%;
    height: auto;
}
/* O AdRotate embrulha cada criativo em .g/.g-single; sem isto o inline-block herda o
   text-align do tema e desalinha do resto da coluna. */
.bahia-pub-retangulo .g-single {
    display: block;
}
CSS;
}

function bahia_pub_enqueue() {
    if (is_admin()) {
        return;
    }
    wp_register_style('bahia-publicidade', false, array(), BAHIA_PUB_VER);
    wp_enqueue_style('bahia-publicidade');
    wp_add_inline_style('bahia-publicidade', bahia_pub_css());
}
add_action('wp_enqueue_scripts', 'bahia_pub_enqueue', 30);
