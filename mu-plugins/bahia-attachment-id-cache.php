<?php
/**
 * Plugin Name: BAHIA - Attachment URL->ID cache (indexado + CDN aware)
 * Description: Curto-circuita attachment_url_to_postid() para URLs de uploads (inclusive as URLs
 *              de CDN/CloudFront do WP Offload Media). Resolve o caminho relativo -> attachment ID
 *              pela tabela INDEXADA wp_as3cf_items (indice uidx_source_path, ~0.03s) em vez do
 *              full-scan de ~16s em wp_postmeta que derrubava o RDS. Cacheia o resultado
 *              (transient + memo por request). Origem: incidente 2026-08-06 (stampede de
 *              _wp_attached_file). Ver td_util::get_attachment_id (delega ao core).
 * Author: bahia.ba
 * Version: 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'pre_attachment_url_to_postid', 'bahia_pre_attachment_url_to_postid', 10, 2 );

/**
 * Short-circuit filter (WP >= 6.7) para attachment_url_to_postid().
 *
 * @param int|null $pre null se ainda nao houve lookup.
 * @param string   $url URL sendo resolvida.
 * @return int|null attachment ID (0 = nao encontrado) para curto-circuitar, ou null p/ o core seguir.
 */
function bahia_pre_attachment_url_to_postid( $pre, $url ) {
	if ( null !== $pre ) {
		return $pre; // outro plugin ja resolveu
	}

	$rel = bahia_att_url_to_relative_path( $url );
	if ( null === $rel || '' === $rel ) {
		return null; // nao e uma URL de uploads -> deixa o core seguir
	}

	static $memo = array();
	if ( array_key_exists( $rel, $memo ) ) {
		return $memo[ $rel ];
	}

	$key    = 'bahia_att_id_' . md5( $rel );
	$cached = get_transient( $key );
	if ( false !== $cached ) {
		$memo[ $rel ] = (int) $cached; // 0 = nao encontrado (cacheado)
		return (int) $cached;
	}

	$post_id = bahia_att_resolve_id( $rel );

	if ( null === $post_id ) {
		// Nao resolvido pelos indices. NUNCA caimos no full-scan de wp_postmeta aqui (foi o
		// que derrubou o RDS). Neste site toda a midia e offloaded (esta em wp_as3cf_items),
		// entao um miss no indice significa que o core tambem so faria um scan sem achar ->
		// curto-circuita com 0 e cacheia. Degrada de forma graciosa (a imagem ainda e servida
		// pela propria URL); o custo evitado e um outage por saturacao de I/O no banco.
		$post_id = 0;
	}

	set_transient( $key, (int) $post_id, 6 * HOUR_IN_SECONDS );
	$memo[ $rel ] = (int) $post_id;
	return (int) $post_id;
}

/**
 * Reduz qualquer URL (CDN ou local) ao caminho relativo de uploads (ex.: 2026/08/bg.png),
 * comparando apenas o PATH (independente do host), pois o core so trata o baseurl do site.
 *
 * @return string|null caminho relativo, ou null se a URL nao aponta para /uploads.
 */
function bahia_att_url_to_relative_path( $url ) {
	if ( ! is_string( $url ) || '' === $url ) {
		return null;
	}
	$uploads = wp_get_upload_dir();
	if ( empty( $uploads['baseurl'] ) ) {
		return null;
	}
	$base = parse_url( $uploads['baseurl'], PHP_URL_PATH ); // ex.: /wp-content/uploads
	$path = parse_url( $url, PHP_URL_PATH );
	if ( ! is_string( $path ) || ! is_string( $base ) || '' === $base ) {
		return null;
	}
	$needle = $base . '/';
	$pos    = strpos( $path, $needle );
	if ( false === $pos ) {
		return null;
	}
	return ltrim( substr( $path, $pos + strlen( $needle ) ), '/' );
}

/**
 * Resolve caminho relativo -> attachment ID usando SOMENTE lookups indexados.
 *
 * @return int|null attachment ID, ou null se nao encontrado pelos indices.
 */
function bahia_att_resolve_id( $rel ) {
	global $wpdb;

	// Candidatos: o proprio caminho e, se for uma variante redimensionada (bg-768x432.png),
	// tambem o original (bg.png), que e o que fica armazenado em source_path/_wp_attached_file.
	$candidates = array( $rel );
	if ( preg_match( '/^(.+)-\d+x\d+(\.[A-Za-z0-9]+)$/', $rel, $m ) ) {
		$candidates[] = $m[1] . $m[2];
	}

	// WP Offload Media: tabela indexada (uidx_source_path). Cobre toda a midia offloaded/CDN.
	static $as3cf_table = null;
	if ( null === $as3cf_table ) {
		$tbl         = $wpdb->prefix . 'as3cf_items';
		$as3cf_table = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $tbl ) ) === $tbl ) ? $tbl : '';
	}

	if ( '' !== $as3cf_table ) {
		foreach ( $candidates as $cand ) {
			$id = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT source_id FROM {$as3cf_table} WHERE source_path = %s AND source_type = 'media-library' LIMIT 1",
					$cand
				)
			);
			if ( $id ) {
				return (int) $id;
			}
		}
	}

	return null;
}
