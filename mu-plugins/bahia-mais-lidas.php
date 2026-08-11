<?php
/**
 * Plugin Name: Bahia.ba - + Mais Lidas (GA4 24h)
 * Description: Alimenta o bloco "+ Mais Lidas" com os posts mais vistos nas
 *              ÚLTIMAS 24H via Google Analytics 4 (Site Kit), cacheado em transient.
 *              Portado de themes/bahia_refactor/mais-lidas-ga4.php (produção usa 48h);
 *              aqui a janela é 24h (mudança intencional). Fallback: SQL do contador
 *              interno 'views' se o GA4/transient estiver indisponível.
 *              Shortcode [bahia_mais_lidas limit="7"] renderiza a lista.
 * Version: 1.0.0
 * Author: Bahia.ba
 */

namespace BahiaNews\MaisLidas;

if (!defined('ABSPATH')) {
    exit;
}

const TRANSIENT_KEY = 'bahia_mais_lidas_ga4_24h_v1';
const CRON_HOOK     = 'bahia_mais_lidas_24h_refresh';
const CRON_INTERVAL = 'bahia_thirty_minutes_ml';
const WINDOW_DAYS   = 1;     // 24h (produção usa 2 = 48h)
const FETCH_LIMIT   = 80;    // linhas do GA4
const STORE_LIMIT   = 30;    // IDs guardados
const TTL_SECONDS   = 21600; // 6h

add_filter('cron_schedules', function ($s) {
    $s[CRON_INTERVAL] = array('interval' => 30 * MINUTE_IN_SECONDS, 'display' => '30 minutos (mais lidas 24h)');
    return $s;
});

add_action('init', function () {
    add_action(CRON_HOOK, __NAMESPACE__ . '\\refresh');
    if (!wp_next_scheduled(CRON_HOOK)) {
        wp_schedule_event(time() + 60, CRON_INTERVAL, CRON_HOOK);
    }
});

/** IDs mais vistos (ordem = mais vistas primeiro). @return int[] */
function get_top_post_ids() {
    $ids = get_transient(TRANSIENT_KEY);
    return is_array($ids) ? $ids : array();
}

/** Atualiza o cache consultando o GA4 via Site Kit (janela 24h). */
function refresh() {
    if (!class_exists('\\Google\\Site_Kit\\Plugin')) {
        return;
    }
    try {
        $context   = \Google\Site_Kit\Plugin::instance()->context();
        $options   = new \Google\Site_Kit\Core\Storage\Options($context);
        $user_opts = new \Google\Site_Kit\Core\Storage\User_Options($context);
        $auth      = new \Google\Site_Kit\Core\Authentication\Authentication($context, $options, $user_opts);

        $owner_id = (int) get_option('googlesitekit_owner_id');
        if (!$owner_id) {
            return;
        }
        $restore = $user_opts->switch_user($owner_id);

        $modules   = new \Google\Site_Kit\Core\Modules\Modules($context, $options, $user_opts, $auth);
        $analytics = $modules->get_module('analytics-4');
        if (!$analytics) {
            $restore();
            return;
        }

        $report = $analytics->get_data('report', array(
            'startDate'  => gmdate('Y-m-d', strtotime('-' . WINDOW_DAYS . ' days')),
            'endDate'    => gmdate('Y-m-d'),
            'metrics'    => array(array('name' => 'screenPageViews')),
            'dimensions' => array('pagePath'),
            'orderby'    => array(array('metric' => array('metricName' => 'screenPageViews'), 'desc' => true)),
            'limit'      => FETCH_LIMIT,
        ));
        $restore();

        if (is_wp_error($report) || !is_object($report) || !method_exists($report, 'getRows')) {
            return;
        }

        $rows     = $report->getRows() ?: array();
        $post_ids = array();
        $excluded = array('page', 'attachment', 'acf', 'mais_noticias');

        foreach ($rows as $row) {
            $dims = $row->getDimensionValues();
            if (empty($dims[0])) {
                continue;
            }
            $path = $dims[0]->getValue();
            if (!$path || '/' === $path) {
                continue;
            }
            $path = strtok($path, '?'); // normaliza querystring
            $pid  = url_to_postid(home_url($path));
            if (!$pid || in_array($pid, $post_ids, true)) {
                continue;
            }
            $post = get_post($pid);
            if (!$post || 'publish' !== $post->post_status || in_array($post->post_type, $excluded, true)) {
                continue;
            }
            $post_ids[] = (int) $pid;
            if (count($post_ids) >= STORE_LIMIT) {
                break;
            }
        }

        if (!empty($post_ids)) {
            set_transient(TRANSIENT_KEY, $post_ids, TTL_SECONDS);
        }
    } catch (\Throwable $e) {
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log('[bahia-mais-lidas-24h] ' . $e->getMessage());
        }
    }
}

/**
 * IDs para a lista, em camadas:
 *  1) GA4 (24h) via transient — fonte primária (produção).
 *  2) SQL: contador 'views' de posts publicados nas últimas 24h (mesmo espírito da prod).
 *  3) Degradação graciosa: posts publicados mais recentes — evita bloco vazio quando
 *     não há GA4 (ex.: homolog, cujo Site Kit é autenticado só no domínio de produção)
 *     nem posts recentes com 'views'.
 */
function resolve_ids($limit) {
    $ids = get_top_post_ids();
    if (!empty($ids)) {
        return array_slice($ids, 0, $limit);
    }

    $editorias = function_exists('bahia_editorias_map') ? array_keys(bahia_editorias_map()) : array();
    $types     = !empty($editorias) ? $editorias : array('post');

    global $wpdb;
    $excl  = "('page','attachment','acf','mais_noticias')";
    $desde = gmdate('Y-m-d H:i:s', strtotime('-1 days')); // 24h

    // Camada 2: mais vistos (views) nas últimas 24h.
    $rows = $wpdb->get_col($wpdb->prepare(
        "SELECT p.ID FROM {$wpdb->posts} p
         INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'views'
         WHERE p.post_status = 'publish' AND p.post_password = ''
           AND p.post_type NOT IN {$excl}
           AND p.post_date >= %s
         ORDER BY (pm.meta_value+0) DESC LIMIT %d",
        $desde, $limit
    ));
    if (!empty($rows)) {
        return array_map('intval', $rows);
    }

    // Camada 3: posts mais recentes (degradação graciosa).
    $q = new \WP_Query(array(
        'post_type'           => $types,
        'post_status'         => 'publish',
        'posts_per_page'      => $limit,
        'orderby'             => 'date',
        'order'               => 'DESC',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'fields'              => 'ids',
    ));
    return array_map('intval', $q->posts);
}

/**
 * Renderiza a lista "+ Mais Lidas".
 *
 * @param int  $limit     quantos itens.
 * @param bool $show_head desenha o cabeçalho próprio ("+ Mais Lidas"). Fica false
 *                        quando o título já vem de um bloco de título do tagDiv —
 *                        é o caso da sidebar da home, onde o padrão visual é o
 *                        título com a linha colorida ao lado, igual às outras seções.
 */
function render($limit = 7, $show_head = true) {
    $ids = resolve_ids($limit);
    if (empty($ids)) {
        return '';
    }
    $q = new \WP_Query(array(
        'post__in'            => $ids,
        'orderby'             => 'post__in',
        'posts_per_page'      => $limit,
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'post_status'         => 'publish',
        'post_type'           => 'any',
    ));
    if (!$q->have_posts()) {
        return '';
    }

    ob_start();
    static $css = false;
    if (!$css) { $css = true; ?>
<style>
/* 48px = ritmo vertical padrão dos blocos da home (valor predominante medido) */
.bahia-ml{margin:0 0 48px;font-family:inherit}
.bahia-ml-head{background:#13182B;color:#fff;font-weight:700;font-size:13px;text-transform:uppercase;letter-spacing:.5px;padding:10px 12px}
.bahia-ml-list{list-style:none;margin:0;padding:0;background:#fff}
.bahia-ml-item{display:flex;align-items:center;gap:10px;padding:10px 12px;border-bottom:1px solid #eef0f3}
.bahia-ml-item:last-child{border-bottom:0}
.bahia-ml-num{flex:0 0 auto;width:22px;height:22px;line-height:22px;text-align:center;background:#4371B6;color:#fff;font-weight:700;font-size:12px;border-radius:3px}
.bahia-ml-thumb{flex:0 0 auto;width:56px;height:42px;overflow:hidden;border-radius:3px;display:block}
.bahia-ml-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.bahia-ml-title{flex:1;font-size:13px;line-height:1.35;color:#13182B !important;font-weight:600;text-decoration:none}
.bahia-ml-title:hover{color:#4371B6 !important}
</style>
<?php } ?>
<div class="bahia-ml">
  <?php if ($show_head) : ?><div class="bahia-ml-head">+ Mais Lidas</div><?php endif; ?>
  <ol class="bahia-ml-list">
    <?php $i = 0; while ($q->have_posts()) : $q->the_post(); $i++;
        $thumb = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail'); ?>
      <li class="bahia-ml-item">
        <span class="bahia-ml-num"><?php echo $i; ?></span>
        <?php if ($thumb) : ?><a class="bahia-ml-thumb" href="<?php the_permalink(); ?>"><img src="<?php echo esc_url($thumb); ?>" alt="" loading="lazy"></a><?php endif; ?>
        <a class="bahia-ml-title" href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
      </li>
    <?php endwhile; ?>
  </ol>
</div>
    <?php
    wp_reset_postdata();
    return ob_get_clean();
}

add_shortcode('bahia_mais_lidas', function ($atts) {
    $atts = shortcode_atts(array('limit' => 7, 'show_head' => 'yes'), $atts, 'bahia_mais_lidas');
    return render(max(1, (int) $atts['limit']), $atts['show_head'] !== 'no');
});
