<?php
/**
 * Plugin Name: Bahia.ba - Selo EXCLUSIVO nos cards dos listings
 * Description: Reproduz no Newspaper o selo que o tema anterior (bahia_refactor,
 *              functions.php:885) exibia como <div class="ui red label">EXCLUSIVO</div>
 *              ao lado do nome da editoria, nos cards das listagens — home, archives,
 *              busca e sidebar.
 *
 *              Origem do dado: campo ACF true/false `exclusivo` (meta_value '1').
 *
 *              POR QUE ASSIM: o badge de editoria (bahia-editoria-tags.php) é um
 *              ::after sobre o .td-image-wrap. Pseudo-elemento não vira irmão de flex,
 *              e a largura do rótulo muda por editoria ("BRASIL" vs "MUNICÍPIOS"), então
 *              não há como encostar um segundo selo ao lado dele só com CSS. Nos cards
 *              exclusivos, portanto, trocamos o pseudo-elemento por DOIS spans reais
 *              numa linha flex — o que também deixa o resultado igual ao de produção,
 *              onde a editoria vem primeiro e o selo vermelho logo à direita.
 *
 *              A marcação entra pelo filtro de saída do bahia-html-saida.php (o módulo
 *              do TagDiv não expõe hook para as classes do card, e plugins/ não se
 *              edita). O que sai do PHP é apenas a lista de posts exclusivos que a
 *              página realmente renderizou — sem varrer o HTML atrás de IDs.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

/** Vermelho do mapa de cores das editorias (o mesmo de Justiça). */
if (!defined('BAHIA_EXCLUSIVO_COR')) {
    define('BAHIA_EXCLUSIVO_COR', '#ff3a2f');
}

/**
 * Registro dos posts exclusivos renderizados nesta requisição: permalink => post_type.
 * Preenchido durante o render dos cards, lido pelo filtro de saída.
 */
function &bahia_exclusivo_registro() {
    static $reg = array();
    return $reg;
}

/** O post está marcado como exclusivo? */
function bahia_exclusivo_e_exclusivo($post_id) {
    return get_post_meta($post_id, 'exclusivo', true) === '1';
}

/**
 * Anota, a cada card montado, se aquele post é exclusivo.
 *
 * Somente LEITURA do $post — diferente do bahia-limites-texto.php, aqui não há
 * nenhuma mutação no objeto, para não vazar estado para o resto da página.
 */
add_filter('td_wp_booster_module_constructor', function ($module, $post = null) {
    // is_admin() é true em admin-ajax.php, que é justamente por onde vêm os cards do
    // "ver mais" — daí a ressalva com wp_doing_ajax().
    if (!$post instanceof WP_Post || (is_admin() && !wp_doing_ajax())) {
        return $module;
    }
    if (!bahia_exclusivo_e_exclusivo($post->ID)) {
        return $module;
    }
    $reg = &bahia_exclusivo_registro();
    $reg[get_permalink($post->ID)] = $post->post_type;
    return $module;
}, 10, 2);

/**
 * Mesma anotação, para os cards que NÃO passam pelos módulos do TagDiv.
 *
 * São dois caminhos de render distintos e sem interseção: os blocos da home/busca/
 * sidebar usam módulos do TagDiv (que não chamam post_class), e os archives de
 * editoria usam themes/Newspaper/loop-archive.php + bahia-scroll-infinito.php (que
 * chamam post_class mas não constroem módulo). Registrar nos dois é o que faz o selo
 * valer em todas as listagens.
 */
add_filter('post_class', function ($classes, $class, $post_id) {
    if ((is_admin() && !wp_doing_ajax()) || !$post_id) {
        return $classes;
    }
    if (bahia_exclusivo_e_exclusivo($post_id)) {
        $reg = &bahia_exclusivo_registro();
        $reg[get_permalink($post_id)] = get_post_type($post_id);
    }
    return $classes;
}, 20, 3);

/** Rótulo da editoria, do mesmo mapa usado pelo badge colorido. */
function bahia_exclusivo_label_editoria($post_type) {
    if (function_exists('bahia_editoria_tags_map')) {
        $mapa = bahia_editoria_tags_map();
        if (isset($mapa[$post_type])) {
            return $mapa[$post_type];
        }
    }
    return '';
}

/** Cores da editoria, do mesmo mapa. */
function bahia_exclusivo_cores_editoria($post_type) {
    $fallback = array('#111111', '#ffffff');
    if (!function_exists('bahia_editoria_tags_colors')) {
        return $fallback;
    }
    $cores = bahia_editoria_tags_colors();
    if (isset($cores[$post_type])) {
        return $cores[$post_type];
    }
    return isset($cores['DEFAULT']) ? $cores['DEFAULT'] : $fallback;
}

/**
 * Injeta os selos nos .td-image-wrap dos posts exclusivos.
 */
add_filter('bahia_hs_html', 'bahia_exclusivo_injetar');

function bahia_exclusivo_injetar($html) {
    $reg = &bahia_exclusivo_registro();
    if (empty($reg) || !is_string($html) || strpos($html, 'td-image-wrap') === false) {
        return $html;
    }

    $novo = preg_replace_callback(
        '#<a\b[^>]*class="[^"]*td-image-wrap[^"]*"[^>]*>#i',
        function ($m) use ($reg) {
            $tag = $m[0];
            // Já processado (ex.: card veio pronto do AJAX): não duplicar.
            if (strpos($tag, 'bahia-tem-selos') !== false) {
                return $tag;
            }
            if (!preg_match('#\bhref="([^"]*)"#i', $tag, $h)) {
                return $tag;
            }
            $href = html_entity_decode($h[1], ENT_QUOTES, 'UTF-8');
            if (!isset($reg[$href])) {
                return $tag;
            }
            $post_type = $reg[$href];
            $label     = bahia_exclusivo_label_editoria($post_type);

            // Marca o wrap para o CSS esconder o ::after (badge de editoria), que
            // passa a ser desenhado como span dentro da linha de selos.
            $tag = preg_replace(
                '#class="([^"]*td-image-wrap[^"]*)"#i',
                'class="$1 bahia-tem-selos"',
                $tag,
                1
            );

            $selos = '<span class="bahia-selos">';
            if ($label !== '') {
                // Prefixo "ed-" de propósito: existe um CPT chamado `exclusivo`, e sem
                // ele a regra da editoria colidiria com .bahia-selo-exclusivo, pintando
                // o selo vermelho de preto.
                $selos .= '<span class="bahia-selo bahia-selo-editoria bahia-selo-ed-' . esc_attr($post_type) . '">'
                        . esc_html($label) . '</span>';
            }
            $selos .= '<span class="bahia-selo bahia-selo-exclusivo">EXCLUSIVO</span></span>';

            return $tag . $selos;
        },
        $html
    );

    return is_string($novo) ? $novo : $html;
}

/* -------------------------------------------------------------------------
 *  CARDS CARREGADOS POR AJAX
 *
 *  O buffer de saída do bahia-html-saida.php cobre o render inicial da página,
 *  mas não o admin-ajax: o "Ver mais" do TagDiv (td_ajax_block/td_ajax_loop) e o
 *  do scroll infinito devolvem JSON com o HTML dos cards dentro, e o TagDiv
 *  encerra com die(json_encode(...)) — sem hook nenhum na saída.
 *
 *  Sem isto, o selo aparecia só nos cards da primeira carga e sumia nos seguintes.
 *
 *  Abre-se um buffer nessas ações e, no fim, o JSON é DECODIFICADO, os campos de
 *  texto recebem a injeção e ele é recodificado. Decodificar (em vez de aplicar
 *  regex no JSON escapado) é o que torna isso confiável: o HTML volta a ser HTML
 *  antes de ser tocado.
 * ------------------------------------------------------------------------- */
function bahia_exclusivo_ajax_buffer() {
    ob_start('bahia_exclusivo_ajax_filtrar');
}
foreach (array('td_ajax_block', 'td_ajax_loop', 'bahia_scroll_infinito') as $acao) {
    add_action('wp_ajax_' . $acao, 'bahia_exclusivo_ajax_buffer', 1);
    add_action('wp_ajax_nopriv_' . $acao, 'bahia_exclusivo_ajax_buffer', 1);
}

function bahia_exclusivo_ajax_filtrar($saida) {
    $reg = &bahia_exclusivo_registro();
    if (empty($reg) || !is_string($saida) || $saida === '') {
        return $saida;
    }
    $dados = json_decode($saida, true);
    if ($dados === null && json_last_error() !== JSON_ERROR_NONE) {
        return $saida; // não é JSON: devolve intacto
    }
    array_walk_recursive($dados, function (&$v) {
        if (is_string($v) && strpos($v, 'td-image-wrap') !== false) {
            $v = bahia_exclusivo_injetar($v);
        }
    });
    $novo = wp_json_encode($dados);
    return is_string($novo) ? $novo : $saida;
}

/**
 * CSS dos selos. Espelha a tipografia do badge de editoria (bahia-editoria-tags.php)
 * para que as duas peças fiquem idênticas em altura e peso.
 */
add_action('wp_enqueue_scripts', function () {
    if (is_admin()) {
        return;
    }
    $vermelho = BAHIA_EXCLUSIVO_COR;

    $css = <<<CSS
/* Nos cards exclusivos a editoria deixa de ser ::after e vira span, para os dois
   selos ficarem lado a lado numa linha flex. */
.td-image-wrap.bahia-tem-selos::after{content:none !important;display:none !important;}

.bahia-selos{
    position:absolute;
    top:0;
    left:0;
    z-index:6;
    display:flex;
    align-items:stretch;
    gap:4px;
    max-width:calc(100% - 8px);
    pointer-events:none;
}
.bahia-selo{
    font-family:'Roboto',Arial,sans-serif;
    font-size:11px;
    font-weight:700;
    line-height:1;
    letter-spacing:.4px;
    text-transform:uppercase;
    padding:4px 8px;
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
}
.bahia-selo-exclusivo{
    background:{$vermelho};
    color:#ffffff;
    flex:0 0 auto;
}
@media (max-width:767px){
    .bahia-selo{font-size:10px;padding:3px 7px;}
    .bahia-selos{gap:3px;}
}
CSS;

    // Cor de cada editoria, do mesmo mapa do badge normal.
    if (function_exists('bahia_editoria_tags_map')) {
        foreach (array_keys(bahia_editoria_tags_map()) as $slug) {
            list($bg, $txt) = bahia_exclusivo_cores_editoria($slug);
            $css .= ".bahia-selo-ed-{$slug}{background:{$bg};color:{$txt};}\n";
        }
    }

    wp_register_style('bahia-exclusivo', false, array(), '1.0.0');
    wp_enqueue_style('bahia-exclusivo');
    wp_add_inline_style('bahia-exclusivo', $css);
}, 31);
