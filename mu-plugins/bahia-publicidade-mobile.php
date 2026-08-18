<?php
/**
 * Plugin Name: Bahia.ba - Slots de publicidade MOBILE (AdRotate)
 * Description: Transporte dos espaços publicitários que o tema legado `bahia_refactor`
 *              entrega no CELULAR e que o Newspaper não entregava.
 *
 *              O achado que motivou o arquivo: no Newspaper o leaderboard do topo vive
 *              dentro de `.td-header-desktop-wrap`, que o tema esconde abaixo de 767px.
 *              Medido em 390px, antes desta rodada:
 *
 *                  data-grupo=12  caixa=0x0  ANCESTRAL display:none -> .td-header-desktop-wrap
 *                  data-grupo=8   caixa=350x252  VISIVEL
 *                  data-grupo=9   caixa=350x252  VISIVEL
 *
 *              Ou seja: os retângulos da coluna lateral apareciam no celular, mas o
 *              leaderboard — grupos 1, 12 e 14, os ÚNICOS com criativo ativo hoje — não.
 *              Era inventário vendido e vivo não entregue onde está a maior parte do
 *              público. O legado entrega: o bloco `#bar` de `header.php:196-210` está
 *              dentro do ramo `is_mobile()`, com o comentário "Banners mobile - fora do
 *              header fixo", e a CSS em ≤900px põe `width:100%` no contêiner e na <img>.
 *
 *              CONFERIDO EM PRODUÇÃO com UA móvel e cache-buster (sem o buster o CDN
 *              devolve a variante de desktop e a leitura sai errada):
 *
 *                  /                -> grupo 1  + grupo 2 (vazio) + 2x grupo 10 (vazio)
 *                  /politica/       -> grupo 12 + grupo 13 (vazio) + 1x grupo 10 (vazio)
 *                  single           -> grupo 12 + grupo 13 (vazio) + 1x grupo 11 (vazio)
 *                  /municipios/     -> grupo 14
 *
 * ESCOPO DECIDIDO NESTA RODADA (as três escolhas foram do usuário, não defaults):
 *
 *   1. Grupos 2 e 13 entram SÓ NO MOBILE. No legado eles vivem no `#bar`, que é
 *      COMPARTILHADO com o desktop (`header.php:401/406` é cópia idêntica do ramo
 *      mobile). Reproduzir isso no desktop quebraria a linha da marca fechada na
 *      rodada 8, calculada no limite: 256px de logo + 728x90 numa row de 1068px deixa
 *      ~20px de folga — não cabe um 320x100 ao lado. Enquanto os grupos estiverem
 *      zerados nada apareceria, e no dia em que o comercial cadastrasse um criativo o
 *      desktop quebraria sozinho, sem ninguém lembrar da origem. A infidelidade ao
 *      legado é consciente: o legado tinha outra estrutura de cabeçalho.
 *      Se um dia o desktop precisar deles, é REDESENHO DE CABEÇALHO, não transporte.
 *
 *   2. Na home o topo leva SÓ o leaderboard; o grupo 2 desce para depois do primeiro
 *      bloco de manchetes. Empilhar os dois antes da primeira manchete é o que o legado
 *      faz, mas em 390px a primeira dobra é o ativo mais escasso da home.
 *
 *   3. O criativo ocupa 100% da largura da coluna (350px em 390px de viewport), como a
 *      CSS do legado faz em ≤900px. Um 728x90 vira 350x43. É o que produção já entrega
 *      hoje, então nenhum anunciante recebe menos do que recebia. A legibilidade baixa
 *      em peça com texto miúdo é problema do CRIATIVO: o caminho é cadastrar peça em
 *      formato mobile (320x100), e esses grupos estão todos zerados — ver
 *      PENDENCIAS-gestores.md.
 *
 * GRUPOS QUE FICAM DE FORA, e por quê:
 *   - grupo 4 ("Home - Formato Proprietário 2", 320x100): a única chamada no legado está
 *     COMENTADA (`bahia_social/index.php:243`, `<//?php`) e em `bahia_refactor` não
 *     existe nenhuma. Mesma situação do grupo 3.
 *   - grupo 7 ("Home-Proprietário 3 SubDestaques", 125x125): NENHUMA chamada em nenhum
 *     dos dois temas, e zero anúncios cadastrados. Não se inventa posição.
 *
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_PUB_MOB_VER', '1.0.0');

/** Acima deste ponto o tema mostra o header desktop e este arquivo não deve pintar nada. */
define('BAHIA_PUB_MOB_BP', 767);

/**
 * Medida de RESERVA de cada grupo, para não haver deslocamento de layout.
 *
 * Não é o tamanho cadastrado no AdRotate: para os grupos 1 e 12 o cadastro diz 970x90 e a
 * realidade é 728x90 (94 de 102 e 96 de 104 criativos, medidos na rodada 8 lendo o
 * cabeçalho dos arquivos). Reservar por 970 deixaria uma faixa vazia permanente.
 */
function bahia_pub_mob_medida($grupo) {
    $m = array(
        1  => array(728, 90),   // Home - Super leader board (cadastro diz 970; real é 728)
        12 => array(728, 90),   // Internas - Leaderboard (idem)
        14 => array(728, 90),   // Leaderboard Municipios
        2  => array(320, 100),  // Home - Formato Proprietário 1
        13 => array(320, 100),  // Internas-Botao_Proprietario
        10 => array(125, 125),  // HomeMobile-1
        11 => array(125, 125),  // InternaMobile-1
    );
    return isset($m[$grupo]) ? $m[$grupo] : null;
}

/**
 * HTML de um slot mobile, ou string vazia.
 *
 * A guarda de vazio é a mesma de `bahia-publicidade.php` e é o requisito crítico da
 * rodada: sem criativo elegível o AdRotate devolve SÓ um comentário HTML —
 * `<!-- Either there are no banners... -->`, 92 bytes, sem nenhum `<div class="g">`.
 * Devolvendo '' aqui, o contêiner não chega a existir e não sobra buraco nenhum no
 * layout. Todos os grupos deste arquivo estão zerados hoje, então este é o caminho que
 * de fato roda em produção até o comercial cadastrar peça.
 */
function bahia_pub_mob_slot($grupo, $pos) {
    if (!function_exists('bahia_pub_grupo_html')) {
        return '';
    }
    $ad = bahia_pub_grupo_html($grupo);
    if ($ad === '') {
        return '';
    }

    // A reserva de altura vive num style inline porque depende do grupo. `aspect-ratio`
    // + `object-fit:contain` garante que a caixa tenha a altura final ANTES de a imagem
    // carregar: criativo fora da medida encaixa dentro da caixa em vez de redimensioná-la.
    $med = bahia_pub_mob_medida($grupo);
    $ar  = $med ? ' style="aspect-ratio:' . $med[0] . '/' . $med[1] . '"' : '';

    return '<div class="bahia-pub-mob bahia-pub-mob--' . esc_attr($pos)
         . '" data-grupo="' . (int) $grupo . '"' . $ar . '>' . $ad . '</div>';
}

/**
 * Grupo do minibanner 320x100, pelo mesmo critério de contexto do leaderboard.
 *
 * `header.php:201` (home) -> 2 ; `header.php:206` (todo o resto) -> 13. Municípios NÃO
 * tem minibanner no legado — o ramo `elseif` só traz o leaderboard 14 —, e essa ausência
 * é transportada como ausência.
 */
function bahia_pub_mob_grupo_minibanner() {
    if (is_front_page()) {
        return 2;
    }
    if (function_exists('bahia_pub_contexto_municipios') && bahia_pub_contexto_municipios()) {
        return 0;
    }
    return 13;
}

/**
 * Grupo do quadrado 125x125, conforme o template do legado que atende o contexto.
 *
 * `single_mobile.php:102` -> 11 ; `archive-mobile.php:79` e
 * `page-ultimas-noticias-mobile.php:41/126` (que é a HOME no legado, via
 * `index.php:2`) -> 10.
 */
function bahia_pub_mob_grupo_quadrado() {
    if (is_singular()) {
        return 11;
    }
    if (is_front_page() || is_archive() || is_home()) {
        return 10;
    }
    return 0;
}

/* -------------------------------------------------------------------------
 * Injeção no HTML montado
 * ---------------------------------------------------------------------- */

/**
 * Por que pelo buffer e não por hook do tema.
 *
 * Os três contextos renderizam por arquivos DIFERENTES — home e páginas por
 * `td-composer/legacy/Newspaper/page.php`, archive por `themes/Newspaper/archive.php`,
 * single por `.../single.php` — e nenhum deles dispara uma action comum antes do
 * conteúdo. `.td-main-content-wrap` é o primeiro nó do corpo nos três (conferido no HTML
 * servido), e é exatamente onde o `#bar` do legado ficava: fora do header, acima do
 * conteúdo.
 *
 * E por que NÃO detectar dispositivo no servidor, como o legado faz com Mobile_Detect:
 * o site serve fastcgi_cache. Uma variante por user-agent envenenaria o cache — a
 * primeira visita decidiria o que todo mundo veria. A separação tem de ser por CSS, que
 * é como o próprio tema já esconde o header desktop abaixo de 767px.
 */
function bahia_pub_mob_injetar($html) {
    if (is_admin() || is_feed() || is_robots()) {
        return $html;
    }

    $topo = bahia_pub_mob_slot(bahia_pub_grupo_topo(), 'topo');

    $mini = '';
    $g_mini = bahia_pub_mob_grupo_minibanner();
    // Na home o minibanner NÃO vai para o topo (decisão 2): lá ele desce para depois do
    // primeiro bloco, e quem o insere é bahia_pub_mob_injetar_meio().
    if ($g_mini && !is_front_page()) {
        $mini = bahia_pub_mob_slot($g_mini, 'mini');
    }

    if ($topo === '' && $mini === '') {
        return $html;
    }

    $bloco = '<div class="bahia-pub-mob-bar">' . $topo . $mini . '</div>';
    $alvo  = '<div class="td-main-content-wrap';
    $pos   = strpos($html, $alvo);
    if ($pos === false) {
        return $html;
    }
    return substr($html, 0, $pos) . $bloco . substr($html, $pos);
}

/**
 * Slots que ficam DEPOIS do conteúdo: o quadrado 125x125 (grupos 10/11) e, só na home, o
 * minibanner do grupo 2.
 *
 * Âncora: o fechamento do primeiro `.td-main-content-wrap`. Como o buffer recebe a página
 * inteira, procurar o `</div>` correto exige contar aninhamento; em vez disso, ancora-se
 * no rodapé, que é um nó único e estável.
 */
function bahia_pub_mob_injetar_fim($html) {
    if (is_admin() || is_feed() || is_robots()) {
        return $html;
    }

    $partes = '';
    if (is_front_page()) {
        $partes .= bahia_pub_mob_slot(2, 'mini');
    }
    $g_quad = bahia_pub_mob_grupo_quadrado();
    if ($g_quad) {
        $partes .= bahia_pub_mob_slot($g_quad, 'quadrado');
    }
    if ($partes === '') {
        return $html;
    }

    $bloco = '<div class="bahia-pub-mob-fim">' . $partes . '</div>';
    foreach (array('<div class="td-footer-wrap', '<footer') as $alvo) {
        $pos = strpos($html, $alvo);
        if ($pos !== false) {
            return substr($html, 0, $pos) . $bloco . substr($html, $pos);
        }
    }
    return $html;
}

add_filter('bahia_hs_html', 'bahia_pub_mob_injetar', 20);
add_filter('bahia_hs_html', 'bahia_pub_mob_injetar_fim', 21);

/* -------------------------------------------------------------------------
 * CSS
 * ---------------------------------------------------------------------- */

function bahia_pub_mob_css() {
    $bp   = BAHIA_PUB_MOB_BP;
    $bp1  = BAHIA_PUB_MOB_BP + 1;
    return <<<CSS
/* Slots de celular (bahia-publicidade-mobile.php).

   Acima de {$bp}px o tema mostra o header desktop, que já traz o leaderboard: aqui tudo
   some, senão o mesmo grupo apareceria duas vezes na mesma página. `display:none` no
   contêiner também evita que o navegador baixe o criativo no desktop. */
@media (min-width: {$bp1}px) {
    .bahia-pub-mob-bar,
    .bahia-pub-mob-fim { display: none !important; }
}
@media (max-width: {$bp}px) {
    /* Os 20px laterais alinham o criativo à COLUNA de conteúdo, não à borda da tela.
       Medido: o recuo do conteúdo é 20px em 360, 390 e 414px — constante —, então o
       criativo fica com 320, 350 e 374px de largura, acompanhando o resto da página.
       Sem isto o slot sangra de ponta a ponta (era 390px numa coluna de 350px), que é
       justamente a alternativa descartada por quebrar o alinhamento. */
    .bahia-pub-mob-bar { padding: 10px 20px 0; }
    .bahia-pub-mob-fim { padding: 0 20px 10px; }
    /* O contêiner só existe quando HÁ criativo (a guarda de vazio devolve '' antes de
       montar a div), então esta margem nunca vira buraco em slot vazio. */
    .bahia-pub-mob {
        margin: 0 auto 10px;
        max-width: 100%;
        line-height: 0;
        text-align: center;
    }
    /* 100% da largura da COLUNA, não da tela: mantém o alinhamento com o conteúdo.
       A altura vem do aspect-ratio inline, por grupo — é o que reserva o espaço antes
       de a imagem carregar e impede o empurrão da primeira manchete. */
    .bahia-pub-mob img {
        width: 100%;
        height: 100%;
        max-width: 100%;
        object-fit: contain;
        display: block;
    }
    .bahia-pub-mob .g,
    .bahia-pub-mob .g-col,
    .bahia-pub-mob .g-single,
    .bahia-pub-mob a {
        display: block;
        width: 100%;
        height: 100%;
    }
    /* Os 125x125 não devem esticar até 350px: viram um quadrado gigante e granulado.
       Ficam no tamanho nativo, centrados. */
    .bahia-pub-mob[data-grupo="10"],
    .bahia-pub-mob[data-grupo="11"] {
        width: 125px;
    }
}
CSS;
}

function bahia_pub_mob_enqueue() {
    if (is_admin()) {
        return;
    }
    wp_register_style('bahia-publicidade-mobile', false, array(), BAHIA_PUB_MOB_VER);
    wp_enqueue_style('bahia-publicidade-mobile');
    wp_add_inline_style('bahia-publicidade-mobile', bahia_pub_mob_css());
}
add_action('wp_enqueue_scripts', 'bahia_pub_mob_enqueue', 31);
