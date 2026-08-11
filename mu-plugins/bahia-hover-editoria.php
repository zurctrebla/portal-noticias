<?php
/**
 * Plugin Name: Bahia.ba - Hover por editoria (título, overlay e texto sobre imagem)
 * Description: Três correções de hover que o demo Magazine PRO entrega na SUA cor de
 *              destaque (#008d7f, verde-água) em vez da cor da editoria:
 *
 *              (3) COR DO TÍTULO NO HOVER — o demo emite, por bloco,
 *                  `body .tdi_NN .td_module_wrap:hover .td-module-title a{color:#008d7f!important}`
 *                  e só em alguns blocos. Aqui a cor passa a vir da editoria do card
 *                  (classe `td-cpt-{slug}` posta por bahia-editoria-tags.php) e vale em
 *                  TODOS os blocos e nos archives.
 *
 *              (4) OVERLAY DA IMAGEM NO HOVER — o demo pinta `.entry-thumb:after` com
 *                  `mix-blend-mode:color`, o que recolore a foto inteira. Três blocos
 *                  trazem isso de fábrica, com três cores do demo:
 *                    tdi_104 (destaques)  rgba(221,51,51,0.25)   vermelho
 *                    tdi_118 (Salvador)   rgba(112,204,63,0.3)   verde
 *                    tdi_129 (Justiça)    #008d7f                verde-água
 *                  Passa a ser a cor da editoria DO CARD — o que importa nos blocos
 *                  mistos (destaques, Últimas Notícias), onde o bloco não tem editoria.
 *
 *              (5) TEXTO SOBRE IMAGEM — nos blocos em que a meta-info é absoluta sobre a
 *                  foto, o título tem de ser branco sempre, inclusive no hover, e a foto
 *                  precisa de um véu que garanta a leitura. O bloco Justiça já vinha com
 *                  véu (`rgba(0,0,0,0.32)`) e lê bem; o de Salvador não tinha nenhum, e é
 *                  daí que vem a ilegibilidade relatada.
 *
 * ---------------------------------------------------------------------------
 * DUAS DECISÕES QUE VALE REGISTRAR
 *
 * 1. Nada de seletor `.tdi_NN`. O tagDiv RENUMERA esses ids a cada edição de template
 *    (a rodada 5 já pagou esse preço com o `text-transform:capitalize` das datas). Tudo
 *    aqui se ancora em `td-cpt-{slug}`, que é nosso, e em classes estruturais do tema.
 *
 * 2. `.td_module_flex_1` NÃO serve para identificar "texto sobre imagem". Ele aparece em
 *    sete blocos da home (Política, Esporte, Entretenimento, Salvador, Municípios, Brasil
 *    e Mundo) e só no de Salvador é que o demo o estiliza com a meta-info absoluta. Usar a
 *    classe do módulo forçaria texto branco em seis blocos de texto sobre fundo branco —
 *    ou seja, títulos invisíveis. Por isso o bloco é marcado em tempo de saída, lendo o
 *    CSS que o PRÓPRIO demo imprime (ver bahia_hover_ed_marcar_blocos). O critério é o
 *    fato observável `position:absolute` na meta-info, não um id que muda.
 *
 * @see bahia-editoria-tags.php  fonte única das cores de editoria
 * @see bahia-html-saida.php     buffer de saída único do site (filtro bahia_hs_html)
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_HOVER_ED_VER', '1.0.0');

/** Opacidade do overlay de hover sobre a foto (item 4). */
if (!defined('BAHIA_HOVER_ED_ALFA')) {
    define('BAHIA_HOVER_ED_ALFA', 0.30);
}

/* -------------------------------------------------------------------------
 * Cor
 * ---------------------------------------------------------------------- */

/** #rrggbb => array(r,g,b). Devolve null se não reconhecer. */
function bahia_hover_ed_rgb($hex) {
    $hex = ltrim((string) $hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-f]{6}$/i', $hex)) {
        return null;
    }
    return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

/** Luminância relativa (WCAG 2.1). */
function bahia_hover_ed_luminancia(array $rgb) {
    $c = array();
    foreach ($rgb as $v) {
        $v = $v / 255;
        $c[] = ($v <= 0.03928) ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

/** Razão de contraste entre duas cores hex. */
function bahia_hover_ed_contraste($a, $b) {
    $ra = bahia_hover_ed_rgb($a);
    $rb = bahia_hover_ed_rgb($b);
    if (!$ra || !$rb) {
        return 1.0;
    }
    $la = bahia_hover_ed_luminancia($ra);
    $lb = bahia_hover_ed_luminancia($rb);
    if ($la < $lb) {
        list($la, $lb) = array($lb, $la);
    }
    return ($la + 0.05) / ($lb + 0.05);
}

/**
 * Versão da cor escurecida o bastante para servir de TEXTO sobre fundo branco (AA, 4,5:1).
 *
 * Escurece multiplicando os canais, o que preserva o matiz — laranja continua laranja,
 * vermelho continua vermelho. Devolve a cor original quando ela já passa.
 *
 * Este é o ponto a mexer se a decisão for outra: devolver `$fallback` sem escurecer
 * mantém a cor padrão de hover nas editorias claras.
 */
function bahia_hover_ed_cor_legivel($hex, $alvo = 4.5, $fallback = '#111111') {
    $rgb = bahia_hover_ed_rgb($hex);
    if (!$rgb) {
        return $fallback;
    }
    if (bahia_hover_ed_contraste($hex, '#ffffff') >= $alvo) {
        return strtolower($hex);
    }
    for ($f = 95; $f >= 5; $f -= 5) {
        $novo = sprintf('#%02x%02x%02x',
            (int) ($rgb[0] * $f / 100), (int) ($rgb[1] * $f / 100), (int) ($rgb[2] * $f / 100));
        if (bahia_hover_ed_contraste($novo, '#ffffff') >= $alvo) {
            return $novo;
        }
    }
    return $fallback;
}

/** "#rrggbb" + alfa => "rgba(r,g,b,a)". */
function bahia_hover_ed_rgba($hex, $alfa) {
    $rgb = bahia_hover_ed_rgb($hex);
    if (!$rgb) {
        return 'rgba(0,0,0,' . $alfa . ')';
    }
    return sprintf('rgba(%d,%d,%d,%s)', $rgb[0], $rgb[1], $rgb[2], rtrim(rtrim(number_format($alfa, 2, '.', ''), '0'), '.'));
}

/* -------------------------------------------------------------------------
 * CSS
 * ---------------------------------------------------------------------- */

function bahia_hover_ed_css() {
    if (!function_exists('bahia_editoria_tags_colors') || !function_exists('bahia_editoria_tags_map')) {
        return ''; // bahia-editoria-tags.php ausente: sem fonte de cores, não inventamos nenhuma
    }
    $cores  = bahia_editoria_tags_colors();
    $labels = bahia_editoria_tags_map();

    $css = "/* itens 3, 4 e 5 — hover na cor da editoria (bahia-hover-editoria.php) */\n";

    // Tudo que depende de PONTEIRO vai para $hover e sai embrulhado em
    // `@media (hover: hover) and (pointer: fine)` no fim da função.
    //
    // Por quê: em tela sensível ao toque não existe "sair de cima". O navegador aplica
    // :hover no elemento tocado e SÓ o solta no toque seguinte em outro lugar — medido
    // aqui com injeção de toque real (Input.dispatchTouchEvent; evento sintetizado por
    // JS não aciona a máquina de hover e daria falso negativo):
    //
    //     REPOUSO         título = rgb(17,17,17)
    //     APÓS TAP        título = rgb(4,46,252)   :hover=true    <- preso
    //     APÓS TAP FORA   título = rgb(17,17,17)   :hover=false
    //
    // Rolagem começando em cima do card NÃO prende (o navegador cancela o hover ao virar
    // gesto de rolagem), então o caso é o toque genuíno.
    //
    // O overlay do item 4 NÃO entra aqui: `.entry-thumb::after` tem `content:none` nos
    // três estados — o pseudo-elemento nunca gera caixa neste módulo, e a regra abaixo só
    // define a COR de um véu que o demo ativa (ou não) por conta própria.
    $hover = '';

    foreach (array_keys($labels) as $slug) {
        $base = isset($cores[$slug]) ? $cores[$slug][0] : $cores['DEFAULT'][0];

        // --- item 3: cor do título no hover -------------------------------
        // O demo usa `body .tdi_NN .td_module_wrap:hover .td-module-title a` com
        // !important — especificidade (0,4,2). A classe repetida 3x leva esta regra a
        // (0,5,2) sem depender de nenhum id .tdi_NN.
        $titulo = bahia_hover_ed_cor_legivel($base);
        $c = ".td-cpt-{$slug}";
        $hover .= "body {$c}{$c}{$c}:hover .td-module-title a,\n"
                . "body {$c}{$c}{$c}:hover .entry-title a{color:{$titulo} !important;}\n";

        // --- item 4: overlay da foto no hover -----------------------------
        // O mais forte do demo é (0,3,3); com o :not() e a classe repetida chegamos a
        // (0,4,3) + !important. `mix-blend-mode:color` recolore a foto inteira, então a
        // cor entra com alfa — em opacidade cheia a imagem viraria monocromática.
        $overlay = bahia_hover_ed_rgba($base, BAHIA_HOVER_ED_ALFA);
        $css .= "html:not([class*='ie']) body {$c}{$c} .entry-thumb:after{background:{$overlay} !important;}\n";
    }

    // Editorias sem classe td-cpt-* (post nativo, página): mantém o preto padrão.
    $def = $cores['DEFAULT'][0];
    $hover .= "\n/* fallback: cards sem editoria */\n"
            . "body .td_module_wrap:hover .td-module-title a{color:" . bahia_hover_ed_cor_legivel($def) . ";}\n";

    // --- item 5: texto sobre imagem ---------------------------------------
    // Os marcadores são postos no bloco em tempo de saída, só onde o demo tornou a
    // meta-info absoluta (ver bahia_hover_ed_marcar_blocos). Sem marcador, nada acontece.
    //
    // Há DOIS escopos, porque o demo escreve a regra de duas formas diferentes:
    //
    //   .bahia-sobre-img + .bahia-sobre-img-mod
    //       o CSS mira UM tipo de módulo dentro de UM bloco
    //       (`.tdi_118 .td_module_flex_1 .td-module-meta-info{position:absolute}`).
    //       Só aqueles cards são sobre imagem; os demais do bloco, não.
    //
    //   .bahia-sobre-img-all
    //       o CSS mira uma classe ESTRUTURAL do tema, valendo para o bloco inteiro
    //       (`.td-big-grid-flex .td-module-meta-info{position:absolute}`). Todos os
    //       cards do bloco são sobre imagem, e não há classe de módulo no seletor
    //       para pendurar o `-mod`.
    //
    // Os dois escopos chegam a (0,6,2) — um degrau acima do item 3, que é (0,5,2).
    $mod = 'body .bahia-sobre-img.bahia-sobre-img.bahia-sobre-img.bahia-sobre-img .bahia-sobre-img-mod';
    $all = 'body .bahia-sobre-img-all.bahia-sobre-img-all.bahia-sobre-img-all'
         . '.bahia-sobre-img-all.bahia-sobre-img-all';

    // A trava do branco tem DUAS metades e elas não podem viajar juntas: a de repouso vale
    // sempre (é ela que mantém o título legível sobre a foto no celular), e só a variante
    // :hover depende de ponteiro. Embrulhar as duas no @media apagaria o texto branco no
    // toque — que é justamente o que o item 5 existe para garantir.
    $branco = array();
    $branco_hover = array();
    foreach (array($mod, $all) as $escopo) {
        foreach (array('.td-module-title a', '.entry-title a') as $alvo) {
            $branco[]       = "{$escopo} {$alvo}";
            $branco_hover[] = "{$escopo}:hover {$alvo}";
        }
    }
    $branco = implode(",\n", $branco);
    // Fica FORA das duas guardas de mídia: em (0,6,2) vence tanto o item 3 (0,5,2), no
    // desktop, quanto o neutralizador de toque (0,5,2). Uma trava só, válida nos dois mundos.
    $branco_hover = implode(",\n", $branco_hover) . "{color:#fff !important;}\n";

    // Escopo curto (sem repetição) para o que não disputa com o item 3.
    $mc = '.bahia-sobre-img .bahia-sobre-img-mod';
    $ac = '.bahia-sobre-img-all';

    $css .= <<<CSS

/* --- item 5: blocos com texto SOBRE a imagem --- */
/* Título branco SEMPRE, inclusive no hover: sobre foto, a cor da editoria do item 3
   deixaria o texto ilegível (era o efeito relatado nos 5 primeiros cards da home, em
   Salvador e em Justiça). O overlay colorido da foto segue valendo — só o texto trava. */
{$branco}{
    color:#fff !important;
}
/* Autor e data também ficam sobre a foto nesses blocos. */
{$mc} .td-post-author-name a,
{$mc} .td-post-author-name span,
{$mc} .td-post-date,
{$mc} .entry-date,
{$ac} .td-post-author-name a,
{$ac} .td-post-author-name span,
{$ac} .td-post-date,
{$ac} .entry-date{
    color:#fff !important;
}
/* Véu de leitura — só no escopo `-mod`. Os blocos `-all` (grid grande) já trazem véu
   próprio do demo em `.td-image-wrap:before` e um segundo por cima escureceria demais.
   Usa `:before`, e NÃO `.td-module-thumb a:after`: naquele markup o <a> É a
   `.td-image-wrap`, e ocupar o `::after` dela apagava o badge de editoria, que se
   apoia em `.td-cpt-{slug} .td-image-wrap::after` (bahia-editoria-tags.php). */
{$mc} .td-image-wrap:before{
    content:'';
    position:absolute;
    top:0;
    left:0;
    width:100%;
    height:100%;
    z-index:1;
    pointer-events:none;
    background:linear-gradient(to top,rgba(0,0,0,.78) 0%,rgba(0,0,0,.55) 30%,rgba(0,0,0,.12) 62%,rgba(0,0,0,0) 100%);
}
/* O texto tem de ficar ACIMA do véu. */
{$mc} .td-module-meta-info{position:absolute;z-index:2;}
/* Sombra discreta: segura a leitura sobre céu claro mesmo com o véu. */
{$mc} .td-module-title a,
{$ac} .td-module-title a,
{$ac} .entry-title a{text-shadow:0 1px 3px rgba(0,0,0,.65);}
CSS;

    // `hover: hover` sozinho não basta: aparelhos híbridos (celular com caneta, tablet com
    // teclado acoplado) respondem `hover: hover` e voltariam a prender o estado no dedo.
    // `pointer: fine` exige um ponteiro de precisão, e a conjunção é o que descreve "tem
    // mouse de verdade".
    $css .= "\n/* --- só onde existe ponteiro: em toque o :hover fica preso até o toque"
          . " seguinte --- */\n"
          . "@media (hover: hover) and (pointer: fine) {\n"
          . $hover
          . "}\n";

    // --- neutralizador de toque -------------------------------------------
    // Desligar SÓ as regras acima não basta, e isso foi medido: sem elas quem assume o
    // :hover é o CSS do próprio demo, que tem cor de hover por bloco. Depois de embrulhar
    // o item 3, o toque passou a pintar #008d7f no archive e rgb(133,161,178) na home —
    // trocou-se a cor da editoria por cor de demo, e o estado continuava preso.
    //
    // Então em toque a regra não some: ela passa a pintar a cor de REPOUSO, e o resultado
    // visível do toque é "nada acontece". (0,5,2) + !important para vencer o demo, que
    // escreve em (0,4,2) + !important.
    //
    // `--td_text_color` é a variável de onde sai a cor de título do tema
    // (`h1>a … h6>a{color:var(--td_text_color,#111111)}`), então o valor é exato no caso
    // geral. RESSALVA medida: nos blocos em que o demo grava a cor por bloco
    // (`.tdi_117 .td_module_flex_4 .td-module-title a{color:#000000}`, 22 cards da home) o
    // repouso é #000 e esta regra devolve #111 — diferença de 17/255, imperceptível
    // (21:1 contra 19,3:1 de contraste sobre branco) e só durante o toque.
    $css .= "\n/* --- em toque, hover pinta a cor de repouso: nada muda ao tocar --- */\n"
          . "@media (hover: none), (pointer: coarse) {\n"
          . "body .td_module_wrap.td_module_wrap.td_module_wrap:hover .td-module-title a,\n"
          . "body .td_module_wrap.td_module_wrap.td_module_wrap:hover .entry-title a{"
          . "color:var(--td_text_color, #111111) !important;}\n"
          . "}\n";

    // Fora das guardas: vale nos dois mundos (ver comentário na montagem de $branco_hover).
    $css .= "\n/* --- título sobre foto: branco também no hover, com ou sem ponteiro --- */\n"
          . $branco_hover;

    return $css;
}

function bahia_hover_ed_enqueue() {
    if (is_admin()) {
        return;
    }
    $css = bahia_hover_ed_css();
    if ($css === '') {
        return;
    }
    // Prioridade 40: depois do bahia-editoria-tags (30), antes do bahia-cores-ui (45).
    wp_register_style('bahia-hover-editoria', false, array(), BAHIA_HOVER_ED_VER);
    wp_enqueue_style('bahia-hover-editoria');
    wp_add_inline_style('bahia-hover-editoria', $css);
}
add_action('wp_enqueue_scripts', 'bahia_hover_ed_enqueue', 40);

/* -------------------------------------------------------------------------
 * Marcação dos blocos com texto sobre imagem (item 5)
 * ---------------------------------------------------------------------- */

/**
 * Marca, no HTML já montado, os blocos em que o demo colocou a meta-info por cima da foto.
 *
 * Por que aqui e não em CSS puro: a informação "este bloco põe o texto sobre a imagem" só
 * existe dentro do <style> que o tagDiv imprime junto do bloco, na forma
 *
 *     .tdi_118 .td_module_flex_1 .td-module-meta-info{position:absolute; ...}
 *
 * Não há classe no markup que distinga esse bloco dos outros seis que usam o mesmo
 * `td_module_flex_1` com o texto ABAIXO da foto. Lendo o CSS do próprio demo, o critério
 * passa a ser o fato observável — e continua valendo depois de o tagDiv renumerar os
 * `.tdi_NN`, porque o número é lido da saída, não fixado no código.
 *
 * Acrescenta `bahia-sobre-img` ao contêiner do bloco e `bahia-sobre-img-mod` a cada card
 * do tipo de módulo afetado.
 */
/**
 * Classes ESTRUTURAIS que põem a meta-info sobre a imagem para o bloco inteiro.
 *
 * Complementa a varredura por `.tdi_NN`: o demo também escreve a regra sem amarrar a
 * bloco nenhum, com uma classe do tema —
 *
 *     .td-big-grid-flex .td-module-meta-info{position:absolute; ...}
 *
 * É daí que vêm os 5 primeiros cards da home e o bloco Justiça. O critério é o mesmo
 * fato observável (`position:absolute` na meta-info); muda só a forma do seletor.
 *
 * O seletor tem de ser EXATAMENTE `.classe .td-module-meta-info`. Sem essa exigência,
 * `.tdi_118 .td_module_flex_1 .td-module-meta-info` casaria com `td_module_flex_1`, que
 * aparece em sete blocos da home — seis deles com o texto sobre fundo branco, que
 * ficariam com título branco invisível. É a armadilha registrada no cabeçalho.
 */
function bahia_hover_ed_classes_sobre_img($html) {
    $classes = array();
    if (!preg_match_all('#<style[^>]*>(.*?)</style>#is', $html, $blocos)) {
        return $classes;
    }
    foreach ($blocos[1] as $css) {
        if (!preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $regras, PREG_SET_ORDER)) {
            continue;
        }
        foreach ($regras as $regra) {
            if (!preg_match('/position\s*:\s*absolute/i', $regra[2])) {
                continue;
            }
            foreach (explode(',', $regra[1]) as $sel) {
                if (!preg_match('/^\s*\.([a-z][a-z0-9_-]*)\s+\.td-module-meta-info\s*$/i', $sel, $m)) {
                    continue;
                }
                $classe = $m[1];
                if (stripos($classe, 'tdi_') === 0)        continue; // por bloco: a varredura 1 cuida
                if (stripos($classe, 'td_module_') === 0)  continue; // módulo, não contêiner
                if (stripos($classe, 'bahia-') === 0)      continue; // nossos próprios marcadores
                $classes[$classe] = true;
            }
        }
    }
    return array_keys($classes);
}

function bahia_hover_ed_marcar_blocos($html) {
    // 1. Quais pares (bloco, módulo) têm meta-info absoluta.
    $alvos = array(); // tdi => array de classes de módulo
    if (preg_match_all(
            '/\.tdi_(\d+)\s+\.(td_module_[a-z0-9_]+)\s+\.td-module-meta-info\s*\{[^}]*position\s*:\s*absolute/i',
            $html, $m, PREG_SET_ORDER)) {
        foreach ($m as $achado) {
            $alvos[$achado[1]][$achado[2]] = true;
        }
    }

    foreach ($alvos as $tdi => $modulos) {
        // 2. Marca o contêiner do bloco (o `td_block_wrap` que carrega o id tdi_NN).
        $reBloco = '/<div\b[^>]*\bclass="[^"]*\btd_block_wrap\b[^"]*\btdi_'
                 . preg_quote($tdi, '/') . '\b[^"]*"/i';
        if (!preg_match($reBloco, $html, $mb, PREG_OFFSET_CAPTURE)) {
            continue;
        }
        $ini = $mb[0][1];
        $html = substr_replace($html, 'bahia-sobre-img ', $ini + strlen('<div class="'), 0);

        // 3. Marca os cards SÓ dentro deste bloco.
        //
        //    A marcação precisa parar na fronteira do bloco. Sem isso, `td_module_flex_1`
        //    — que aparece em sete blocos da home — sairia marcado em todos, e bastaria
        //    uma regra futura que esquecesse o ancestral `.bahia-sobre-img` para pintar
        //    de branco títulos que ficam sobre fundo branco.
        //
        //    O fim do bloco é aproximado pelo início do PRÓXIMO `td_block_wrap`, e não
        //    por casar a `</div>` correspondente, que regex não faz. É seguro aqui porque
        //    blocos do tagDiv não se aninham: são irmãos dentro da linha (`td-pb-row`).
        $depois = $ini + strlen($mb[0][0]);
        $fim    = strpos($html, 'td_block_wrap', $depois);
        if ($fim === false) {
            $fim = strlen($html);
        }

        $trecho = substr($html, $depois, $fim - $depois);
        foreach (array_keys($modulos) as $mod) {
            $trecho = preg_replace(
                '/(<div\b[^>]*\bclass="[^"]*\b' . preg_quote($mod, '/') . '\b)/i',
                '$1 bahia-sobre-img-mod',
                $trecho
            );
        }
        $html = substr_replace($html, $trecho, $depois, $fim - $depois);
    }

    // 4. Blocos inteiros sobre imagem, marcados por classe estrutural do tema.
    //
    //    Aqui não há classe de módulo no seletor, então o bloco recebe
    //    `bahia-sobre-img-all` e o CSS vale para todos os cards dentro dele — que é o
    //    caso: a regra é do bloco inteiro, não de um tipo de card.
    foreach (bahia_hover_ed_classes_sobre_img($html) as $classe) {
        // `td_block_wrap` e a classe podem vir em qualquer ordem no atributo (em
        // `.td-big-grid-flex` a estrutural vem ANTES), daí os dois lookaheads. O
        // terceiro evita marcar de novo um bloco que a varredura 1 já pegou.
        $re = '/<div\b[^>]*\bclass="'
            . '(?=[^"]*\btd_block_wrap\b)'
            . '(?=[^"]*\b' . preg_quote($classe, '/') . '\b)'
            . '(?![^"]*\bbahia-sobre-img\b)'
            . '[^"]*"/i';
        $html = preg_replace_callback($re, function ($mm) {
            $pos = strpos($mm[0], 'class="') + strlen('class="');
            return substr_replace($mm[0], 'bahia-sobre-img bahia-sobre-img-all ', $pos, 0);
        }, $html);
    }

    return $html;
}
add_filter('bahia_hs_html', 'bahia_hover_ed_marcar_blocos');
