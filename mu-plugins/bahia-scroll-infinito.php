<?php
/**
 * Plugin Name: Bahia.ba - Scroll Infinito (archives de editoria)
 * Description: Substitui a paginação numérica (1 2 3 ... N) dos archives das editorias
 *              (CPTs politica, salvador, esporte, ...) por carregamento incremental:
 *                - Desktop: botão "Ver mais notícias" (no lugar da paginação numérica)
 *                - Mobile:  scroll infinito automático (sem botão)
 *              Comportamento portado do tema antigo bahia_refactor
 *              (assets/js/infinite-scroll-editoria.js + load-more-home.js e o handler
 *              AJAX bahia_infinite_scroll em functions.php), mas reaproveitando o markup
 *              visual do Newspaper (card `td_module_1` de loop-archive.php). Estratégia
 *              baseada em exclude_ids (post__not_in), do mais recente para o mais antigo.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

if (!defined('ABSPATH')) {
    exit;
}

define('BAHIA_SI_VER', '1.0.0');

/**
 * Slugs das editorias (CPTs com has_archive). Reutiliza o mapa do mu-plugin
 * bahia-editorias-cpt.php (carregado antes deste, ordem alfabética 'e' < 's').
 * Fallback: descobre CPTs públicos com archive caso a função não exista.
 */
function bahia_si_editoria_slugs() {
    if (function_exists('bahia_editorias_map')) {
        return array_keys(bahia_editorias_map());
    }
    $slugs = get_post_types(array('has_archive' => true, 'public' => true), 'names');
    unset($slugs['post'], $slugs['page'], $slugs['attachment']);
    return array_values($slugs);
}

/**
 * A página atual é um archive de editoria alvo?
 * Cobre o archive do CPT (/politica) e as taxonomias {slug}_cat / {slug}_tag.
 * Devolve o post_type consultado (string) ou false.
 */
function bahia_si_current_post_type() {
    if (is_admin()) {
        return false;
    }
    $slugs = bahia_si_editoria_slugs();

    if (is_post_type_archive($slugs)) {
        $pt = get_query_var('post_type');
        if (is_array($pt)) {
            $pt = reset($pt);
        }
        return in_array($pt, $slugs, true) ? $pt : false;
    }

    foreach ($slugs as $s) {
        if (is_tax($s . '_cat') || is_tax($s . '_tag')) {
            return $s;
        }
    }
    return false;
}

/**
 * Renderiza UM card no formato do Newspaper (fiel a loop-archive.php -> td_module_1),
 * já embrulhado em `.td-block-span6`. Deve ser chamado dentro do loop (the_post()).
 */
function bahia_si_render_card() {
    $post_thumbnail_url = '';
    if (get_the_post_thumbnail_url(null, 'medium_large') != false) {
        $post_thumbnail_url = get_the_post_thumbnail_url(null, 'medium_large');
    } else {
        $post_thumbnail_url = get_template_directory_uri() . '/images/no-thumb/medium_large.png';
    }

    $thumbnail_id = get_post_thumbnail_id();
    $alt = get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true);
    if (empty($alt)) {
        $alt = the_title_attribute(array('echo' => false));
    }
    ?>
    <div class="td-block-span6">
        <div <?php post_class('td_module_1 td_module_wrap clearfix'); ?>>
            <div class="td-module-image">
                <div class="td-module-thumb">
                    <a href="<?php the_permalink(); ?>" rel="bookmark" class="td-image-wrap" title="<?php the_title_attribute(); ?>">
                        <img class="entry-thumb" src="<?php echo esc_url($post_thumbnail_url); ?>" alt="<?php echo esc_attr($alt); ?>" title="<?php the_title_attribute(); ?>" />
                    </a>
                </div>

                <?php
                    $categories = get_the_category();
                    if (!empty($categories)) {
                        $cat_link = get_category_link($categories[0]->cat_ID);
                        $cat_name = $categories[0]->name; ?>
                        <a class="td-post-category" href="<?php echo esc_url($cat_link); ?>"><?php echo esc_html($cat_name); ?></a>
                <?php } ?>
            </div>

            <h3 class="entry-title td-module-title">
                <a href="<?php the_permalink(); ?>" rel="bookmark" title="<?php the_title_attribute(); ?>">
                    <?php the_title(); ?>
                </a>
            </h3>

            <div class="td-module-meta-info">
                <div class="td-post-author-name">
                    <a href="<?php echo esc_url(get_author_posts_url(get_the_author_meta('ID'))); ?>"><?php echo get_the_author_meta('display_name'); ?></a>
                    <span> - </span>
                </div>

                <span class="td-post-date">
                    <time class="entry-date updated td-module-date" datetime="<?php echo esc_html(date(DATE_W3C, get_the_time('U'))); ?>"><?php the_time(get_option('date_format')); ?></time>
                </span>

                <div class="td-module-comments">
                    <a href="<?php comments_link(); ?>">
                        <?php comments_number('0', '1', '%'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php
}

/**
 * Enfileira o JS/CSS e injeta os dados da página apenas nos archives alvo.
 */
function bahia_si_enqueue() {
    // Home (front page): o feed principal é um bloco TagDiv (tdb_loop_2). Usamos o
    // "load more" nativo do TagDiv (query/cards corretos) e só reestilizamos o botão +
    // scroll infinito no mobile. Ver bahia_si_home_enqueue().
    if (is_front_page() || is_home()) {
        bahia_si_home_enqueue();
        return;
    }

    // Busca (?s=...): resultados são um bloco TagDiv tdb_loop (template 547291), agora
    // com ajax_pagination=load_more. Mesmo tratamento da home (load more nativo), só
    // com selector e rótulo próprios. Reaproveita a query de busca do TagDiv (FULLTEXT).
    if (is_search()) {
        bahia_si_loadmore_enqueue('.tdb_loop', __('Ver mais resultados', 'newspaper'));
        return;
    }

    $post_type = bahia_si_current_post_type();
    if (!$post_type) {
        return;
    }

    global $wp_query;
    $per_page = (int) $wp_query->get('posts_per_page');
    if ($per_page <= 0) {
        $per_page = (int) get_option('posts_per_page', 10);
    }
    $total_posts = (int) $wp_query->found_posts;
    $exclude_ids = wp_list_pluck($wp_query->posts, 'ID');

    // taxonomia atual (se for archive de taxonomia da editoria)
    $taxonomy = '';
    $term_id  = 0;
    if (is_tax()) {
        $term = get_queried_object();
        if ($term && isset($term->taxonomy)) {
            $taxonomy = $term->taxonomy;
            $term_id  = (int) $term->term_id;
        }
    }

    // Loader do TagDiv (mesmo do "load more" da home) — nos archives esse JS não é
    // carregado; enfileiramos p/ manter o padrão visual de loading. tdUtil e o CSS
    // (.td-lb-box) já existem site-wide; falta só o tdLoadingBox.js.
    $deps = array('jquery');
    $tdlb_base = defined('TDC_SCRIPTS_URL') ? TDC_SCRIPTS_URL
        : (defined('WP_PLUGIN_URL') ? WP_PLUGIN_URL . '/td-composer/legacy/Newspaper/js' : '');
    if ($tdlb_base !== '') {
        wp_enqueue_script('bahia-td-loading-box', $tdlb_base . '/tdLoadingBox.js', array('jquery'), null, true);
        $deps[] = 'bahia-td-loading-box';
    }

    // Handle "dummy" apenas para pendurar inline script/style + localize.
    wp_register_script('bahia-si', false, $deps, BAHIA_SI_VER, true);
    wp_enqueue_script('bahia-si');

    wp_localize_script('bahia-si', 'bahiaScrollInfinito', array(
        'ajaxUrl'      => admin_url('admin-ajax.php'),
        'nonce'        => wp_create_nonce('bahia_scroll_infinito'),
        'postType'     => $post_type,
        'taxonomy'     => $taxonomy,
        'termId'       => $term_id,
        'perPage'      => $per_page,
        'totalPosts'   => $total_posts,
        'excludeIds'   => array_map('intval', $exclude_ids),
        'mobileQuery'  => '(max-width: 767px)',
        'scrollOffset' => 600, // px do fim para disparar o carregamento no mobile
        'i18n'         => array(
            'loadMore' => __('Ver mais notícias', 'newspaper'),
            'loading'  => __('Carregando...', 'newspaper'),
            'noMore'   => __('Você chegou ao fim das notícias.', 'newspaper'),
            'error'    => __('Erro ao carregar. Toque para tentar novamente.', 'newspaper'),
        ),
    ));

    // Handle "dummy" (src=false) só para pendurar o CSS inline.
    wp_register_style('bahia-si-css', false);
    wp_enqueue_style('bahia-si-css');
    wp_add_inline_style('bahia-si-css', bahia_si_inline_css());

    wp_add_inline_script('bahia-si', bahia_si_inline_js());
}
add_action('wp_enqueue_scripts', 'bahia_si_enqueue', 20);

function bahia_si_inline_css() {
    return <<<CSS
.bahia-si-controls{clear:both;text-align:center;margin:6px 0 34px;padding:0 14px;}
.bahia-load-more-btn{display:inline-block;cursor:pointer;font-family:'Roboto',Arial,sans-serif;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;line-height:1;color:#fff;background-color:#222;padding:15px 32px;border:0;border-radius:0;transition:background-color .2s ease;-webkit-appearance:none;}
.bahia-load-more-btn:hover{background-color:#000;}
.bahia-load-more-btn:disabled,.bahia-load-more-btn.is-loading{opacity:.6;cursor:default;}
/* Loader = mesmo do TagDiv/home (.td-loader-gif.td-loader-infinite se auto-centraliza
   via position:absolute;left:50%;margin-left:-16px dentro deste box relativo). */
.bahia-si-loaderbox{position:relative;display:none;height:40px;margin:14px 0 4px;}
.bahia-si-nomore{display:none;color:#999;font-family:'Roboto',Arial,sans-serif;font-size:12px;text-transform:uppercase;letter-spacing:.5px;padding:18px 0;}
@media (max-width:767px){.bahia-load-more-btn{display:none;}}
CSS;
}

function bahia_si_inline_js() {
    return <<<'JS'
(function ($) {
    'use strict';
    var D = window.bahiaScrollInfinito;
    if (!D) { return; }

    var state = {
        loading: false,
        hasMore: true,
        loadedIds: new Set(),
        totalPosts: D.totalPosts || 0
    };

    (D.excludeIds || []).forEach(function (id) {
        id = parseInt(id, 10);
        if (id > 0) { state.loadedIds.add(id); }
    });

    var isMobile = function () {
        return window.matchMedia && window.matchMedia(D.mobileQuery).matches;
    };

    var LOADER_COLOR = window.tds_theme_color_site_wide || '#4db2ec';
    function showLoader() {
        $loader.show();
        if (window.tdLoadingBox) { tdLoadingBox.init(LOADER_COLOR, 45); }
    }
    function hideLoader() {
        if (window.tdLoadingBox) { tdLoadingBox.stop(); }
        $loader.hide();
    }

    var $pageNav, $container, $controls, $btn, $loader, $nomore;

    function build() {
        $pageNav = $('.td-ss-main-content .page-nav').first();
        // container onde vivem as linhas de posts
        var $lastRow = $('.td-ss-main-content .td-block-row').last();
        if ($lastRow.length === 0) { return false; }
        $container = $lastRow.parent();

        $controls = $('<div class="bahia-si-controls"></div>');
        $btn = $('<button type="button" class="bahia-load-more-btn"></button>').text(D.i18n.loadMore);
        // Loader idêntico ao "load more" da home (TagDiv tdLoadingBox).
        $loader = $('<div class="bahia-si-loaderbox" aria-hidden="true"><div class="td-loader-gif td-loader-infinite"></div></div>');
        $nomore = $('<div class="bahia-si-nomore"></div>').text(D.i18n.noMore);
        $controls.append($btn).append($loader).append($nomore);

        // Substitui a paginação numérica pelo bloco de controles.
        if ($pageNav.length) {
            $pageNav.replaceWith($controls);
        } else {
            $lastRow.after($controls);
        }
        return true;
    }

    function updateHasMore() {
        if (state.totalPosts > 0) {
            state.hasMore = state.loadedIds.size < state.totalPosts;
        }
        if (!state.hasMore) {
            $btn.hide();
            $nomore.fadeIn(300);
        }
    }

    function loadMore() {
        if (state.loading || !state.hasMore) { return; }
        state.loading = true;
        $btn.prop('disabled', true).addClass('is-loading');
        showLoader();

        $.ajax({
            url: D.ajaxUrl,
            type: 'POST',
            dataType: 'json',
            timeout: 15000,
            data: {
                action: 'bahia_scroll_infinito',
                nonce: D.nonce,
                post_type: D.postType,
                taxonomy: D.taxonomy || '',
                term_id: D.termId || 0,
                posts_per_page: D.perPage,
                exclude_ids: Array.from(state.loadedIds).join(',')
            },
            success: function (res) {
                if (!res || !res.success || !res.data || !res.data.html || res.data.count === 0) {
                    state.hasMore = false;
                    updateHasMore();
                    return;
                }
                var data = res.data;
                (data.ids || []).forEach(function (id) {
                    id = parseInt(id, 10);
                    if (id > 0) { state.loadedIds.add(id); }
                });
                $(data.html).insertBefore($controls);
                if (typeof data.total_posts !== 'undefined') {
                    state.totalPosts = parseInt(data.total_posts, 10) || state.totalPosts;
                }
                state.hasMore = !!data.has_more && state.loadedIds.size < state.totalPosts;
                updateHasMore();

                // No mobile, se ainda estiver perto do fim, continua carregando.
                if (isMobile() && state.hasMore) {
                    setTimeout(maybeLoadOnScroll, 250);
                }
            },
            error: function () {
                $btn.text(D.i18n.error);
            },
            complete: function () {
                state.loading = false;
                $btn.prop('disabled', false).removeClass('is-loading');
                hideLoader();
            }
        });
    }

    function maybeLoadOnScroll() {
        if (state.loading || !state.hasMore || !isMobile()) { return; }
        var scrollBottom = $(window).scrollTop() + $(window).height();
        var trigger = $(document).height() - (D.scrollOffset || 600);
        if (scrollBottom >= trigger) {
            loadMore();
        }
    }

    function debounce(fn, wait) {
        var t;
        return function () {
            clearTimeout(t);
            t = setTimeout(fn, wait);
        };
    }

    $(function () {
        if (!build()) { return; }

        // Estado inicial (ex.: já carregou tudo).
        if (state.totalPosts > 0 && state.loadedIds.size >= state.totalPosts) {
            state.hasMore = false;
            $btn.hide();
            $nomore.show();
            return;
        }

        // Desktop: clique no botão. (No mobile o CSS esconde o botão.)
        $btn.on('click', loadMore);

        // Mobile: scroll infinito.
        $(window).on('scroll.bahiaSi', debounce(maybeLoadOnScroll, 150));
        setTimeout(maybeLoadOnScroll, 500);
    });
})(jQuery);
JS;
}

/* -------------------------------------------------------------------------
 *  LOAD MORE nativo do TagDiv (bloco tdb_loop) — usado na HOME e na BUSCA.
 *  A paginação numérica do bloco foi trocada por ajax_pagination=load_more
 *    - Home  (page 547281):      feed principal .tdb_loop_2  -> "Ver mais notícias"
 *    - Busca (template 547291):  resultados     .tdb_loop    -> "Ver mais resultados"
 *  Aqui só reestilizamos o botão (visual = botão do archive) e, no mobile,
 *  escondemos o botão e disparamos o clique automaticamente ao chegar perto do
 *  fim (scroll infinito). O carregamento (query + cards + imagens) é 100% do
 *  TagDiv — na busca, isso preserva a query FULLTEXT nativa.
 * ------------------------------------------------------------------------- */
function bahia_si_home_enqueue() {
    // Feed principal da home.
    bahia_si_loadmore_enqueue('.tdb_loop_2', __('Ver mais notícias', 'newspaper'));
}

/**
 * Enfileira CSS/JS de load-more + scroll infinito mobile para um bloco tdb_loop.
 * @param string $main_selector selector do bloco de loop principal (ex: .tdb_loop_2)
 * @param string $label         rótulo do botão (ex: "Ver mais notícias")
 */
function bahia_si_loadmore_enqueue($main_selector, $label) {
    wp_register_style('bahia-si-css', false);
    wp_enqueue_style('bahia-si-css');
    wp_add_inline_style('bahia-si-css', bahia_si_loadmore_css($main_selector));

    wp_register_script('bahia-si', false, array('jquery'), BAHIA_SI_VER, true);
    wp_enqueue_script('bahia-si');
    wp_localize_script('bahia-si', 'bahiaScrollInfinitoHome', array(
        'mobileQuery'  => '(max-width: 767px)',
        'scrollOffset' => 600,
        'label'        => $label,
        'mainSelector' => $main_selector,
    ));
    wp_add_inline_script('bahia-si', bahia_si_home_js());
}

function bahia_si_loadmore_css($sel) {
    // Escopo: bloco de loop principal ($sel). !important porque o TagDiv
    // imprime CSS por-bloco inline no corpo, depois do <head>.
    return <<<CSS
$sel .td-load-more-wrap{clear:both;text-align:center !important;margin:31px 0 34px !important;}
$sel a.td_ajax_load_more{display:inline-block !important;float:none !important;width:auto !important;height:auto !important;line-height:1 !important;cursor:pointer;font-family:'Roboto',Arial,sans-serif !important;font-size:11px !important;font-weight:700 !important;text-transform:uppercase !important;letter-spacing:.6px !important;color:#fff !important;background-color:#222 !important;padding:15px 32px !important;border:0 !important;border-radius:0 !important;transition:background-color .2s ease;}
$sel a.td_ajax_load_more:hover{background-color:#000 !important;color:#fff !important;}
$sel a.td_ajax_load_more .td-load-more-icon{display:none !important;}
/* Mobile = scroll infinito: sem botão "load more" (some também em blocos
   secundários tipo "Mais Populares"). */
@media (max-width:767px){.td-load-more-wrap{display:none !important;}}
CSS;
}

function bahia_si_home_js() {
    return <<<'JS'
(function ($) {
    'use strict';
    var D = window.bahiaScrollInfinitoHome;
    if (!D) { return; }

    var Q = D.mobileQuery;
    var LABEL = D.label || 'Ver mais notícias';
    var OFFSET = D.scrollOffset || 600;
    // Todos os botões "load more" do TagDiv na página (feed/resultados + "Mais Populares"…) —
    // usados para trocar o texto em inglês.
    var SEL_ALL = 'a.td_ajax_load_more';
    // Só o loop principal (home: .tdb_loop_2 / busca: .tdb_loop) dispara scroll infinito no mobile.
    var SEL_MAIN = (D.mainSelector || '.tdb_loop_2') + ' .td-load-more-wrap a.td_ajax_load_more';

    function isMobile() {
        return window.matchMedia && window.matchMedia(Q).matches;
    }

    // Troca "Load more" -> "Ver mais notícias" em TODOS os botões da home (o TagDiv
    // re-renderiza o botão a cada carga; por isso reaplica via MutationObserver).
    function relabel() {
        document.querySelectorAll(SEL_ALL).forEach(function (a) {
            if (a.getAttribute('data-bahia-relabel') === '1') { return; }
            a.textContent = LABEL;
            a.setAttribute('data-bahia-relabel', '1');
        });
    }

    var cooling = false;
    function maybeScroll() {
        if (!isMobile() || cooling) { return; }
        var btn = document.querySelector(SEL_MAIN);
        if (!btn) { return; } // sem botão = não há mais posts
        var scrollBottom = $(window).scrollTop() + $(window).height();
        if (scrollBottom >= $(document).height() - OFFSET) {
            cooling = true;
            btn.click(); // dispara o load more nativo do TagDiv (funciona mesmo oculto)
            // Sem re-disparo recursivo: o próximo carregamento depende de novo scroll
            // do usuário (evita cargas em cascata quando parado no fim da página).
            setTimeout(function () { cooling = false; }, 1300);
        }
    }

    function debounce(fn, w) {
        var t;
        return function () { clearTimeout(t); t = setTimeout(fn, w); };
    }

    $(function () {
        relabel();
        // Observa a área de conteúdo inteira (não só o feed) p/ re-rotular também o botão
        // de "Mais Populares" quando o TagDiv o re-renderiza.
        var host = document.querySelector('.td-main-content-wrap') || document.body;
        new MutationObserver(debounce(relabel, 60)).observe(host, { childList: true, subtree: true });
        $(window).on('scroll.bahiaSiHome', debounce(maybeScroll, 150));
        setTimeout(maybeScroll, 600);
    });
})(jQuery);
JS;
}

/**
 * Handler AJAX: devolve os próximos cards (formato Newspaper) a partir de exclude_ids.
 * Do mais recente para o mais antigo (post_date DESC), mesma fonte dos CPTs.
 */
function bahia_si_ajax_handler() {
    if (!check_ajax_referer('bahia_scroll_infinito', 'nonce', false)) {
        wp_send_json_error(array('message' => 'Nonce inválido'));
    }

    $slugs = bahia_si_editoria_slugs();

    $post_type      = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';
    $posts_per_page = isset($_POST['posts_per_page']) ? absint($_POST['posts_per_page']) : 10;
    $exclude_str    = isset($_POST['exclude_ids']) ? sanitize_text_field(wp_unslash($_POST['exclude_ids'])) : '';
    $taxonomy       = isset($_POST['taxonomy']) ? sanitize_key($_POST['taxonomy']) : '';
    $term_id        = isset($_POST['term_id']) ? absint($_POST['term_id']) : 0;

    if (!in_array($post_type, $slugs, true)) {
        wp_send_json_error(array('message' => 'Post type inválido'));
    }

    $posts_per_page = max(1, min($posts_per_page, 20));

    $exclude_ids = array();
    if ($exclude_str !== '') {
        $exclude_ids = array_filter(array_map('absint', explode(',', $exclude_str)));
    }

    $args = array(
        'post_type'              => $post_type,
        'post_status'            => 'publish',
        'posts_per_page'         => $posts_per_page,
        'orderby'                => 'date',
        'order'                  => 'DESC',
        'ignore_sticky_posts'    => true,
        'no_found_rows'          => false,
        'update_post_meta_cache' => true,
        'update_post_term_cache' => true,
    );
    if (!empty($exclude_ids)) {
        $args['post__not_in'] = $exclude_ids;
    }

    // Taxonomia da editoria ({slug}_cat / {slug}_tag).
    $allowed_tax = array($post_type . '_cat', $post_type . '_tag');
    if ($taxonomy !== '' && in_array($taxonomy, $allowed_tax, true) && $term_id > 0) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => $taxonomy,
                'field'    => 'term_id',
                'terms'    => $term_id,
            ),
        );
    }

    $query = new WP_Query($args);

    $response = array(
        'html'        => '',
        'ids'         => array(),
        'count'       => 0,
        'has_more'    => false,
        'total_posts' => (int) $query->found_posts + count($exclude_ids),
    );

    if ($query->have_posts()) {
        ob_start();
        $col = 0;
        while ($query->have_posts()) {
            $query->the_post();
            if ($col === 0) {
                echo '<div class="td-block-row">';
            }
            $response['ids'][] = get_the_ID();
            $response['count']++;
            bahia_si_render_card();
            $col++;
            if ($col === 2) {
                echo '</div>';
                $col = 0;
            }
        }
        if ($col !== 0) {
            echo '</div>'; // fecha linha ímpar
        }
        $response['html'] = ob_get_clean();

        $total_loaded = count($exclude_ids) + $response['count'];
        $response['has_more'] = $total_loaded < $response['total_posts'];
    }

    wp_reset_postdata();
    wp_send_json_success($response);
}
add_action('wp_ajax_bahia_scroll_infinito', 'bahia_si_ajax_handler');
add_action('wp_ajax_nopriv_bahia_scroll_infinito', 'bahia_si_ajax_handler');
