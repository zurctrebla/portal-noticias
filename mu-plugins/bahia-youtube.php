<?php
/**
 * Plugin Name: Bahia YouTube (keyless)
 * Description: Bloco de vídeos do YouTube alimentado pelo feed RSS público do canal (sem YouTube Data API key). Shortcode [bahia_youtube].
 * Author: bahia.ba
 * Version: 1.0
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Busca os últimos vídeos de um canal via feed RSS público (keyless).
 * Cacheado em transient. Retorna array de ['id','title'].
 */
function bahia_yt_get_videos( $channel_id, $limit = 6 ) {
	$channel_id = preg_replace( '/[^A-Za-z0-9_\-]/', '', (string) $channel_id );
	if ( $channel_id === '' ) { return array(); }

	$cache_key = 'bahia_yt_' . $channel_id;
	$cached    = get_transient( $cache_key );
	if ( is_array( $cached ) ) {
		return array_slice( $cached, 0, $limit );
	}

	$url = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . $channel_id;
	$res = wp_remote_get( $url, array( 'timeout' => 8 ) );
	if ( is_wp_error( $res ) || wp_remote_retrieve_response_code( $res ) !== 200 ) {
		// mantém o último cache bom se existir; senão, vazio por 5 min p/ não martelar
		set_transient( $cache_key, is_array( $cached ) ? $cached : array(), 5 * MINUTE_IN_SECONDS );
		return is_array( $cached ) ? array_slice( $cached, 0, $limit ) : array();
	}

	$body = wp_remote_retrieve_body( $res );
	$videos = array();
	$prev = libxml_use_internal_errors( true );
	$xml = simplexml_load_string( $body );
	libxml_use_internal_errors( $prev );

	if ( $xml !== false ) {
		$yt = $xml->children( 'http://www.youtube.com/xml/schemas/2015' );
		foreach ( $xml->entry as $entry ) {
			$ns  = $entry->children( 'http://www.youtube.com/xml/schemas/2015' );
			$vid = (string) $ns->videoId;
			if ( $vid === '' ) { continue; }
			$videos[] = array(
				'id'    => $vid,
				'title' => (string) $entry->title,
			);
			if ( count( $videos ) >= 15 ) { break; }
		}
	}

	// cache 1h (feed muda pouco); se vazio, cache curto p/ retry
	set_transient( $cache_key, $videos, empty( $videos ) ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS );
	return array_slice( $videos, 0, $limit );
}

/**
 * Shortcode [bahia_youtube channel_id="UC..." limit="7" title="Vídeos"]
 * Replica o layout do widget de vídeo do tagDiv: player grande em cima +
 * playlist em grade embaixo, com clique-pra-tocar (JS) e destaque do item ativo.
 * Tudo keyless (embed + thumbnail públicos).
 */
function bahia_yt_shortcode( $atts ) {
	$a = shortcode_atts( array(
		'channel_id' => 'UCgKpV6P4ks_T5QpGn0BxCsQ', // @bahiapontoba
		'limit'      => 7,
		'title'      => '',
		'accent'     => '#dd3333',
	), $atts, 'bahia_youtube' );

	$limit  = max( 2, (int) $a['limit'] );
	$videos = bahia_yt_get_videos( $a['channel_id'], $limit );
	if ( empty( $videos ) ) { return ''; }

	$current = $videos[0];
	$accent  = esc_attr( $a['accent'] );
	$uid     = 'byt_' . substr( md5( $a['channel_id'] . microtime() ), 0, 8 );

	ob_start(); ?>
	<div class="bahia-yt" id="<?php echo esc_attr( $uid ); ?>">
		<?php if ( $a['title'] !== '' ) : ?>
			<h3 class="bahia-yt-title"><span><?php echo esc_html( $a['title'] ); ?></span></h3>
		<?php endif; ?>

		<div class="bahia-yt-flex">
			<div class="bahia-yt-player">
				<div class="bahia-yt-embed">
					<iframe class="bahia-yt-frame"
						src="https://www.youtube.com/embed/<?php echo esc_attr( $current['id'] ); ?>?rel=0&amp;enablejsapi=1"
						title="<?php echo esc_attr( $current['title'] ); ?>"
						loading="lazy" allowfullscreen
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe>
					<div class="bahia-yt-now"><?php echo esc_html( $current['title'] ); ?></div>
				</div>
			</div>

			<div class="bahia-yt-list">
				<?php foreach ( $videos as $i => $v ) : ?>
				<button type="button"
					class="bahia-yt-item<?php echo $i === 0 ? ' is-playing' : ''; ?>"
					data-vid="<?php echo esc_attr( $v['id'] ); ?>"
					data-title="<?php echo esc_attr( $v['title'] ); ?>">
					<span class="bahia-yt-thumb" style="background-image:url('https://i.ytimg.com/vi/<?php echo esc_attr( $v['id'] ); ?>/mqdefault.jpg')">
						<i class="bahia-yt-play"></i>
						<span class="bahia-yt-badge">Assistindo</span>
					</span>
					<span class="bahia-yt-item-title"><?php echo esc_html( $v['title'] ); ?></span>
				</button>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
	<style>
	#<?php echo $uid; ?>.bahia-yt{background:#111417;color:#fff}
	#<?php echo $uid; ?> .bahia-yt-title{margin:0;padding:11px 14px;font-size:15px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;background:<?php echo $accent; ?>}
	#<?php echo $uid; ?> .bahia-yt-flex{display:flex;align-items:stretch}
	#<?php echo $uid; ?> .bahia-yt-player{flex:0 0 66.666%;max-width:66.666%}
	#<?php echo $uid; ?> .bahia-yt-embed{position:relative;width:100%;padding-top:56.25%;background:#000}
	#<?php echo $uid; ?> .bahia-yt-frame{position:absolute;top:0;left:0;width:100%;height:100%;border:0}
	#<?php echo $uid; ?> .bahia-yt-now{position:absolute;left:0;right:0;bottom:0;padding:22px 14px 10px;font-size:14px;font-weight:600;line-height:1.3;color:#fff;background:linear-gradient(transparent,rgba(0,0,0,.85));display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
	#<?php echo $uid; ?> .bahia-yt-list{flex:1 1 33.333%;min-width:0;overflow-y:auto;background:#111417;position:relative}
	#<?php echo $uid; ?> .bahia-yt-list{position:absolute;top:0;bottom:0;right:0;width:33.333%}
	#<?php echo $uid; ?> .bahia-yt-flex{position:relative}
	#<?php echo $uid; ?> .bahia-yt-item{display:flex;gap:9px;align-items:flex-start;width:100%;padding:9px 10px;background:#111417;border:0;border-bottom:1px solid #22282d;text-align:left;cursor:pointer;color:#fff;font:inherit}
	#<?php echo $uid; ?> .bahia-yt-item:hover{background:#191d21}
	#<?php echo $uid; ?> .bahia-yt-thumb{flex:0 0 92px;width:92px;height:52px;background-size:cover;background-position:center;position:relative;border-radius:2px;overflow:hidden}
	#<?php echo $uid; ?> .bahia-yt-play{position:absolute;inset:0;margin:auto;width:0;height:0;border-style:solid;border-width:7px 0 7px 12px;border-color:transparent transparent transparent rgba(255,255,255,.92);transition:opacity .15s}
	#<?php echo $uid; ?> .bahia-yt-badge{position:absolute;left:0;right:0;bottom:0;display:none;background:<?php echo $accent; ?>;color:#fff;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;text-align:center;padding:2px 0}
	#<?php echo $uid; ?> .bahia-yt-item-title{font-size:12px;line-height:1.32;font-weight:500;color:#e8e8e8;display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden}
	#<?php echo $uid; ?> .bahia-yt-item:hover .bahia-yt-item-title{color:#fff}
	#<?php echo $uid; ?> .bahia-yt-item.is-playing{background:#191d21}
	#<?php echo $uid; ?> .bahia-yt-item.is-playing .bahia-yt-play{opacity:0}
	#<?php echo $uid; ?> .bahia-yt-item.is-playing .bahia-yt-badge{display:block}
	#<?php echo $uid; ?> .bahia-yt-item.is-playing .bahia-yt-item-title{color:<?php echo $accent; ?>;font-weight:700}
	#<?php echo $uid; ?> .bahia-yt-list::-webkit-scrollbar{width:6px}
	#<?php echo $uid; ?> .bahia-yt-list::-webkit-scrollbar-thumb{background:#3a4149;border-radius:3px}
	@media (max-width:767px){
		#<?php echo $uid; ?> .bahia-yt-flex{display:block;position:static}
		#<?php echo $uid; ?> .bahia-yt-player{flex:none;max-width:100%}
		#<?php echo $uid; ?> .bahia-yt-list{position:static;width:100%;max-height:280px}
	}
	</style>
	<script>
	(function(){
		var root=document.getElementById('<?php echo $uid; ?>');
		if(!root||root.dataset.bound)return; root.dataset.bound='1';
		var frame=root.querySelector('.bahia-yt-frame');
		var now=root.querySelector('.bahia-yt-now');
		root.querySelectorAll('.bahia-yt-item').forEach(function(btn){
			btn.addEventListener('click',function(){
				var id=btn.getAttribute('data-vid');
				frame.src='https://www.youtube.com/embed/'+id+'?rel=0&autoplay=1&enablejsapi=1';
				if(now)now.textContent=btn.getAttribute('data-title');
				root.querySelectorAll('.bahia-yt-item.is-playing').forEach(function(e){e.classList.remove('is-playing');});
				btn.classList.add('is-playing');
			});
		});
	})();
	</script>
	<?php
	return ob_get_clean();
}
add_shortcode( 'bahia_youtube', 'bahia_yt_shortcode' );
