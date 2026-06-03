<?php
/**
 * Mais Lidas — fonte de dados via Google Analytics 4 (Site Kit).
 *
 * Busca periodicamente as páginas mais vistas nas últimas 48h via GA4
 * e armazena os IDs de post correspondentes em um transient consumido
 * por mais_lidas2() em functions.php. Em caso de falha (Site Kit ausente,
 * sem autenticação, erro de API), o consumidor faz fallback para a
 * consulta SQL legada.
 */

namespace Bahia\MaisLidas;

const TRANSIENT_KEY = 'bahia_mais_lidas_ga4_v1';
const CRON_HOOK     = 'bahia_mais_lidas_ga4_refresh';
const CRON_INTERVAL = 'bahia_thirty_minutes';
const WINDOW_DAYS   = 2;   // 48h
const FETCH_LIMIT   = 80;  // GA4 rows
const STORE_LIMIT   = 30;  // post IDs guardados
const TTL_SECONDS   = 21600; // 6h

add_filter( 'cron_schedules', function ( $schedules ) {
	$schedules[ CRON_INTERVAL ] = array(
		'interval' => 30 * MINUTE_IN_SECONDS,
		'display'  => '30 minutos',
	);
	return $schedules;
} );

add_action( 'init', function () {
	add_action( CRON_HOOK, __NAMESPACE__ . '\\refresh' );
	if ( ! wp_next_scheduled( CRON_HOOK ) ) {
		wp_schedule_event( time() + 60, CRON_INTERVAL, CRON_HOOK );
	}
} );

/**
 * Lê o cache de IDs (ordem = mais vistas primeiro).
 *
 * @return int[]
 */
function get_top_post_ids() {
	$ids = get_transient( TRANSIENT_KEY );
	return is_array( $ids ) ? $ids : array();
}

/**
 * Atualiza o cache consultando o GA4 via Site Kit.
 */
function refresh() {
	if ( ! class_exists( '\\Google\\Site_Kit\\Plugin' ) ) {
		return;
	}

	try {
		$context   = \Google\Site_Kit\Plugin::instance()->context();
		$options   = new \Google\Site_Kit\Core\Storage\Options( $context );
		$user_opts = new \Google\Site_Kit\Core\Storage\User_Options( $context );
		$auth      = new \Google\Site_Kit\Core\Authentication\Authentication( $context, $options, $user_opts );

		$owner_id = (int) get_option('googlesitekit_owner_id');
		if ( ! $owner_id ) {
			return;
		}

		// Carrega o token OAuth do usuário "dono" do Site Kit.
		$restore = $user_opts->switch_user( $owner_id );

		$modules   = new \Google\Site_Kit\Core\Modules\Modules( $context, $options, $user_opts, $auth );
		$analytics = $modules->get_module( 'analytics-4' );

		if ( ! $analytics ) {
			$restore();
			return;
		}

		$report = $analytics->get_data(
			'report',
			array(
				'startDate'  => gmdate( 'Y-m-d', strtotime( '-' . WINDOW_DAYS . ' days' ) ),
				'endDate'    => gmdate( 'Y-m-d' ),
				'metrics'    => array( array( 'name' => 'screenPageViews' ) ),
				'dimensions' => array( 'pagePath' ),
				'orderby'    => array(
					array(
						'metric' => array( 'metricName' => 'screenPageViews' ),
						'desc'   => true,
					),
				),
				'limit'      => FETCH_LIMIT,
			)
		);

		$restore();

		if ( is_wp_error( $report ) || ! is_object( $report ) || ! method_exists( $report, 'getRows' ) ) {
			return;
		}

		$rows     = $report->getRows() ?: array();
		$post_ids = array();
		$excluded = array( 'page', 'attachment', 'acf', 'mais_noticias' );

		foreach ( $rows as $row ) {
			$dims = $row->getDimensionValues();
			if ( empty( $dims[0] ) ) {
				continue;
			}
			$path = $dims[0]->getValue();
			if ( ! $path || '/' === $path ) {
				continue;
			}

			$url = home_url( $path );
			$pid = url_to_postid( $url );
			if ( ! $pid || in_array( $pid, $post_ids, true ) ) {
				continue;
			}

			$post = get_post( $pid );
			if ( ! $post || 'publish' !== $post->post_status ) {
				continue;
			}
			if ( in_array( $post->post_type, $excluded, true ) ) {
				continue;
			}

			$post_ids[] = (int) $pid;
			if ( count( $post_ids ) >= STORE_LIMIT ) {
				break;
			}
		}

		if ( ! empty( $post_ids ) ) {
			set_transient( TRANSIENT_KEY, $post_ids, TTL_SECONDS );
		}
	} catch ( \Throwable $e ) {
		// Silencia: fallback SQL será usado em mais_lidas2().
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			error_log( '[mais-lidas-ga4] ' . $e->getMessage() );
		}
	}
}
