<?php
/**
 * Plugin Name: Bahia.ba - Tags de editoria coloridas nos cards
 * Description: Exibe, em cada card de post (blocos TagDiv), uma tag com o nome da
 *              EDITORIA (o post_type/CPT) e cor por editoria — no lugar da categoria
 *              interna do Newspaper (que quase nunca existe nos CPTs e, quando existe,
 *              mostra um termo errado tipo "Brasil" num post de política).
 *              Abordagem 100% CSS: cada card tem a classe `td-cpt-{editoria}`; um
 *              ::after sobre a `.td-module-thumb` desenha a tag. Sem JS ⇒ sem flash e
 *              cobre também os cards carregados via "Ver mais"/scroll infinito.
 *              Cores (bloco 4):
 *                Últimas/fallback=preto, Política=azul, Dendê e Poder=laranja,
 *                Municípios=amarelo, Justiça=vermelho, Esporte=verde vivo,
 *                Brasil=verde escuro, Entretenimento=roxo, Salvador=azul claro,
 *                Mundo=cinza claro (texto escuro), Artigos=cinza-azulado.
 *              Editorias fora da lista herdam o preto (contexto "Últimas Notícias").
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_EDITORIA_TAGS_VER', '1.0.0');

/**
 * Cor por editoria (slug do CPT => [background, texto]). Slugs ausentes usam o
 * fallback preto (DEFAULT). Rótulo vem do bahia_editorias_map() (label oficial).
 */
function bahia_editoria_tags_colors() {
    return array(
        'politica'       => array('#042efc', '#ffffff'), // azul
        'dende_poder'    => array('#e8710a', '#ffffff'), // laranja (mantido)
        'municipios'     => array('#e49600', '#222222'), // amarelo/âmbar (texto escuro p/ contraste)
        'justica'        => array('#ff3a2f', '#ffffff'), // vermelho
        'esporte'        => array('#00bc0d', '#ffffff'), // verde vivo
        'brasil'         => array('#0b6b2e', '#ffffff'), // verde escuro (6,65:1) — fora do manual
        'entretenimento' => array('#5300b1', '#ffffff'), // roxo
        'salvador'       => array('#4db2ec', '#ffffff'), // azul claro (mantido)
        // Mundo é a única tag de fundo claro: usa o cinza + azul profundo do manual
        // (#EDEDED/#13182B, 15,3:1). A exceção à regra de texto branco é proposital —
        // é o que separa Mundo de Artigos por luminosidade, não só por tom de cinza.
        'mundo'          => array('#ededed', '#13182b'),
        'artigo'         => array('#5b6470', '#ffffff'), // cinza-azulado (6,1:1); #808080 reprovava AA
        // DEFAULT (Últimas Notícias / demais editorias): preto
        'DEFAULT'        => array('#111111', '#ffffff'),
    );
}

/**
 * Editorias (slug => label). Reutiliza o mapa do bahia-editorias-cpt.php;
 * fallback mínimo se indisponível.
 */
function bahia_editoria_tags_map() {
    if (function_exists('bahia_editorias_map')) {
        $out = array();
        foreach (bahia_editorias_map() as $slug => $ed) {
            $out[$slug] = $ed['label'];
        }
        return $out;
    }
    return array(
        'politica' => 'Política', 'salvador' => 'Salvador', 'municipios' => 'Municípios',
        'justica' => 'Justiça', 'esporte' => 'Esporte', 'entretenimento' => 'Entretenimento',
        'artigo' => 'Artigos', 'dende_poder' => 'Dendê e Poder',
    );
}

function bahia_editoria_tags_css() {
    $colors = bahia_editoria_tags_colors();
    $labels = bahia_editoria_tags_map();
    list($def_bg, $def_txt) = $colors['DEFAULT'];

    // Seletores base (posicionamento + tipografia), agrupados p/ todas as editorias.
    // Ancoramos em `.td-image-wrap::after` (e NÃO em `.td-module-thumb::after`)
    // porque o Newspaper já usa `.td-module-thumb::after` para o overlay de hover
    // (width/height:100%) com maior especificidade — reusar aquele ::after fazia o
    // badge cobrir a imagem inteira. `.td-image-wrap::after` está livre em todos os
    // tipos de card (hero flex, feed, "Mais Populares").
    $base_selectors = array();
    foreach (array_keys($labels) as $slug) {
        $base_selectors[] = '.td-cpt-' . $slug . ' .td-image-wrap::after';
    }
    $base = implode(",\n", $base_selectors);

    $css = <<<CSS
/* garante ancoragem do badge sobre a imagem */
[class*="td-cpt-"] .td-image-wrap{position:relative;}
/* esconde a categoria interna nativa do Newspaper nos cards de CPT (rótulo errado) */
[class*="td-cpt-"] a.td-post-category{display:none !important;}

/* base do badge de editoria */
$base{
    content:'';
    position:absolute;
    top:0;
    left:0;
    right:auto;
    bottom:auto;
    width:auto;
    height:auto;
    z-index:5;
    pointer-events:none;
    padding:4px 8px;
    font-family:'Roboto',Arial,sans-serif;
    font-size:11px;
    font-weight:700;
    line-height:1;
    letter-spacing:.4px;
    text-transform:uppercase;
    background:$def_bg;
    color:$def_txt;
    white-space:nowrap;
    max-width:calc(100% - 8px);
    overflow:hidden;
    text-overflow:ellipsis;
}
@media (max-width:767px){
    $base{font-size:10px;padding:3px 7px;}
}

CSS;

    // Regra por editoria: conteúdo (label) + cores. Não-listadas herdam o preto (base).
    foreach ($labels as $slug => $label) {
        $label_css = addcslashes($label, "'\\");
        $bg = $def_bg; $txt = $def_txt;
        if (isset($colors[$slug])) {
            list($bg, $txt) = $colors[$slug];
        }
        $css .= ".td-cpt-{$slug} .td-image-wrap::after{content:'{$label_css}';background:{$bg};color:{$txt};}\n";
    }

    return $css;
}

/**
 * Garante a classe `td-cpt-{editoria}` nos cards que NÃO passam pelos módulos do TagDiv.
 *
 * Os módulos do tagDiv já põem essa classe sozinhos, e é dela que dependem tanto o badge
 * colorido acima quanto o selo EXCLUSIVO. Mas os archives de editoria são renderizados
 * por themes/Newspaper/loop-archive.php (e o "ver mais" por bahia-scroll-infinito.php),
 * que chamam post_class() puro — por isso os cards dos archives apareciam sem badge
 * nenhum, ao contrário dos da home.
 *
 * Só no front-end e só para os CPTs de editoria; posts nativos e páginas ficam intactos.
 */
add_filter('post_class', function ($classes, $class, $post_id) {
    // wp_doing_ajax: admin-ajax.php faz is_admin() devolver true, e sem esta ressalva
    // os cards trazidos pelo "ver mais" saíam sem a classe — badge só na 1ª carga.
    if (is_admin() && !wp_doing_ajax()) {
        return $classes;
    }
    $pt = get_post_type($post_id);
    if (!$pt || !array_key_exists($pt, bahia_editoria_tags_map())) {
        return $classes;
    }
    $alvo = 'td-cpt-' . $pt;
    if (!in_array($alvo, $classes, true)) {
        $classes[] = $alvo;
    }
    return $classes;
}, 10, 3);

function bahia_editoria_tags_enqueue() {
    if (is_admin()) {
        return;
    }
    wp_register_style('bahia-editoria-tags', false, array(), BAHIA_EDITORIA_TAGS_VER);
    wp_enqueue_style('bahia-editoria-tags');
    wp_add_inline_style('bahia-editoria-tags', bahia_editoria_tags_css());
}
add_action('wp_enqueue_scripts', 'bahia_editoria_tags_enqueue', 30);
