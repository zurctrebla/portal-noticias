<?php
/**
 * Plugin Name: Bahia - Reconciliacao de offload S3
 * Description: Reenvia ao S3 anexos que ficaram sem registro em wp_as3cf_items.
 * Author: bahia.ba
 *
 * POR QUE ISTO EXISTE
 *
 * O offload do WP Offload Media roda no hook wp_update_attachment_metadata,
 * prioridade 110 -- o ultimo passo da cadeia de upload. Tudo o mais (geracao de
 * miniaturas, WP Smush) roda antes. Se a requisicao morre no meio, o anexo fica
 * criado, a metadata salva e as miniaturas geradas, mas o arquivo nunca vai
 * para o S3. A assinatura aparece no banco: anexos com miniaturas parciais
 * (3 de 12, 5 de 12) e sem linha em wp_as3cf_items.
 *
 * Em 10/08/2026 havia 107 anexos nesse estado, espalhados de 2015 a 2026, e o
 * caso e mais comum em imagens muito grandes (40-96 MP), cuja geracao de
 * miniaturas custa 24-35s de CPU.
 *
 * Nao foi possivel determinar com certeza o que interrompe a requisicao --
 * memoria (pico de 107 MB contra limite de 512M) e timeout (24-35s contra
 * limite de 300s) foram medidos e descartados. Esta rotina trata a consequencia
 * e nao a causa, de proposito: ela fecha a classe inteira do problema seja qual
 * for o motivo da interrupcao, e e idempotente.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bahia_Offload_Reconciliation {

	const HOOK      = 'bahia_offload_reconcile';
	const SCHEDULE  = 'bahia_hourly';
	const BATCH     = 5;
	const MAX_TRIES = 3;
	const META_TRIES = '_bahia_offload_tries';
	const META_ERROR = '_bahia_offload_last_error';

	/**
	 * Origem de fallback para arquivos que nao existem mais em disco.
	 *
	 * Com remove-local-file ligado, o arquivo local e apagado apos o offload --
	 * entao um anexo que falhou ha meses nao tem copia local nenhuma. A VPS
	 * antiga (pre-migracao para o EKS) ainda serve esses arquivos por HTTP e e a
	 * unica origem para o acervo legado.
	 *
	 * DESLIGADA POR PADRAO -- e o motivo NAO e alcancabilidade.
	 *
	 * A versao anterior deste comentario dizia que 172.31.0.178 estava "em outra
	 * VPC e provavelmente inalcancavel a partir do EKS", e que ligar a origem so
	 * renderia timeouts. Medido em 18/08/2026 de dentro do pod de producao:
	 *
	 *     172.31.0.178:80    -> ABERTA
	 *     172.31.70.197:3306 -> ABERTA   (o proprio RDS de producao)
	 *
	 * A faixa 172.31/16 e o VPC default, onde vive o banco de producao; os pods
	 * falam com ele o dia inteiro. A VPS Docker Swarm anterior esta de pe e e
	 * alcancavel. Corrigido aqui porque comentario errado envelhece pior que
	 * codigo errado: ninguem o testa.
	 *
	 * O motivo real de ficar desligada e que a VPS esta em vias de ser encerrada.
	 * Reconciliar contra uma origem que vai sumir troca um erro visivel (arquivo
	 * ausente, registrado no log) por um silencioso (copia feita de uma fonte
	 * obsoleta, que para de funcionar sem aviso no dia do desligamento).
	 *
	 * Com origem vazia a rotina segue reconciliando normalmente todo arquivo que
	 * exista em disco (EFS) e apenas registra o que nao encontrou, sem tocar na
	 * rede (ver o `continue` em copy_missing_files_locally()).
	 *
	 * Para reativar enquanto a VPS existir, defina a constante no wp-config.php:
	 *
	 *     define( 'BAHIA_OFFLOAD_FALLBACK_ORIGIN', 'http://172.31.0.178' );
	 *
	 * ou, em runtime, via o filtro `bahia_offload_fallback_origin`.
	 */
	public static function fallback_origin() {
		$origin = defined( 'BAHIA_OFFLOAD_FALLBACK_ORIGIN' )
			? BAHIA_OFFLOAD_FALLBACK_ORIGIN
			: '';

		return apply_filters( 'bahia_offload_fallback_origin', $origin );
	}

	public static function init() {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_schedule' ) );
		add_action( self::HOOK, array( __CLASS__, 'run' ) );

		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time() + 300, self::SCHEDULE, self::HOOK );
		}
	}

	public static function add_schedule( $schedules ) {
		$schedules[ self::SCHEDULE ] = array(
			'interval' => HOUR_IN_SECONDS,
			'display'  => 'A cada hora (reconciliacao de offload)',
		);

		return $schedules;
	}

	/**
	 * Anexos sem linha em wp_as3cf_items, ignorando os que ja esgotaram tentativas.
	 */
	public static function pending( $limit ) {
		global $wpdb;

		$sql = $wpdb->prepare(
			"SELECT p.ID
			   FROM {$wpdb->posts} p
			   LEFT JOIN {$wpdb->prefix}as3cf_items i
			          ON i.source_id = p.ID AND i.source_type = 'media-library'
			   LEFT JOIN {$wpdb->postmeta} t
			          ON t.post_id = p.ID AND t.meta_key = %s
			  WHERE p.post_type = 'attachment'
			    AND i.source_id IS NULL
			    AND ( t.meta_value IS NULL OR CAST( t.meta_value AS UNSIGNED ) < %d )
			  ORDER BY p.post_date DESC
			  LIMIT %d",
			self::META_TRIES,
			self::MAX_TRIES,
			$limit
		);

		return array_map( 'intval', $wpdb->get_col( $sql ) );
	}

	public static function has_offload( $id ) {
		global $wpdb;

		return (bool) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}as3cf_items WHERE source_id = %d AND source_type = 'media-library'",
				$id
			)
		);
	}

	/**
	 * Garante o arquivo em disco: usa o local se existir, senao busca na origem
	 * de fallback. Traz tambem as derivadas, para o offload nao subir so o
	 * original e deixar as miniaturas para tras.
	 */
	protected static function ensure_files( $id, &$log ) {
		$base = get_post_meta( $id, '_wp_attached_file', true );
		if ( ! $base ) {
			$log = 'sem _wp_attached_file';
			return false;
		}

		$uploads = wp_upload_dir();
		$meta    = wp_get_attachment_metadata( $id );
		$dir     = dirname( $base );

		$files = array( basename( $base ) );
		if ( ! empty( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$files[] = $size['file'];
				}
			}
		}
		$files = array_values( array_unique( $files ) );

		$origin = self::fallback_origin();
		$got    = 0;

		foreach ( $files as $file ) {
			$rel  = ( '.' === $dir ) ? $file : $dir . '/' . $file;
			$dest = $uploads['basedir'] . '/' . $rel;

			if ( file_exists( $dest ) && filesize( $dest ) > 0 ) {
				$got++;
				continue;
			}

			if ( ! $origin ) {
				continue;
			}

			if ( ! wp_mkdir_p( dirname( $dest ) ) ) {
				$log = 'nao criou o diretorio ' . dirname( $dest );
				return false;
			}

			// Host explicito: a origem antiga responde por IP, mas so serve o
			// site com o Host correto.
			$response = wp_remote_get(
				$origin . '/wp-content/uploads/' . str_replace( ' ', '%20', $rel ),
				array(
					'timeout'  => 120,
					'headers'  => array( 'Host' => 'bahia.ba' ),
					'stream'   => true,
					'filename' => $dest,
				)
			);

			if ( is_wp_error( $response ) ) {
				@unlink( $dest );
				continue;
			}

			if ( 200 !== wp_remote_retrieve_response_code( $response ) || ! filesize( $dest ) ) {
				@unlink( $dest );
				continue;
			}

			$got++;
		}

		if ( ! $got ) {
			$log = 'arquivo ausente em disco e na origem de fallback';
			return false;
		}

		// O original precisa existir: sem ele o offload nao tem o que registrar.
		$original = $uploads['basedir'] . '/' . $base;
		if ( ! file_exists( $original ) ) {
			$log = 'original ausente (apenas derivadas recuperadas)';
			return false;
		}

		return true;
	}

	/**
	 * Reprocessa um anexo. Reaproveita o hook do proprio WP Offload Media
	 * (wp_update_attachment_metadata, prioridade 110) em vez de reimplementar o
	 * upload -- assim o registro em wp_as3cf_items sai exatamente como o plugin
	 * espera, inclusive o caminho versionado por data.
	 */
	public static function reconcile( $id ) {
		if ( self::has_offload( $id ) ) {
			return array( true, 'ja offloadeado' );
		}

		$log = '';
		if ( ! self::ensure_files( $id, $log ) ) {
			return array( false, $log );
		}

		$meta = wp_get_attachment_metadata( $id );
		if ( ! is_array( $meta ) ) {
			return array( false, 'metadata ausente' );
		}

		wp_update_attachment_metadata( $id, $meta );

		if ( self::has_offload( $id ) ) {
			return array( true, 'offload concluido' );
		}

		return array( false, 'hook rodou mas nao criou registro em as3cf_items' );
	}

	public static function run( $batch = null ) {
		$batch = $batch ? (int) $batch : self::BATCH;
		$ids   = self::pending( $batch );

		if ( ! $ids ) {
			return array( 'processados' => 0, 'ok' => 0, 'falhas' => 0 );
		}

		$ok = 0;
		$falhas = 0;

		foreach ( $ids as $id ) {
			list( $success, $motivo ) = self::reconcile( $id );

			if ( $success ) {
				$ok++;
				delete_post_meta( $id, self::META_TRIES );
				delete_post_meta( $id, self::META_ERROR );
				continue;
			}

			$falhas++;

			// Conta tentativas para nao ficar reprocessando o mesmo anexo
			// insoluvel a cada hora. Depois de MAX_TRIES ele sai da fila.
			$tries = (int) get_post_meta( $id, self::META_TRIES, true );
			update_post_meta( $id, self::META_TRIES, $tries + 1 );
			update_post_meta( $id, self::META_ERROR, $motivo );

			error_log(
				sprintf(
					'[bahia-offload-reconcile] anexo %d falhou (tentativa %d/%d): %s',
					$id,
					$tries + 1,
					self::MAX_TRIES,
					$motivo
				)
			);
		}

		error_log(
			sprintf(
				'[bahia-offload-reconcile] lote: %d processados, %d ok, %d falhas',
				count( $ids ),
				$ok,
				$falhas
			)
		);

		return array( 'processados' => count( $ids ), 'ok' => $ok, 'falhas' => $falhas );
	}
}

Bahia_Offload_Reconciliation::init();
