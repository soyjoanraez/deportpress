<?php
/**
 * Votació MVP per partit.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Finestra de votació i tancament automàtic.
 */
class ED_MVP {

	private const META_ABIERTA = '_ed_mvp_votacion_abierta';
	private const META_CIERRE  = '_ed_mvp_votacion_cierre';

	/**
	 * Minuts de votació (filtre ed_mvp_votacion_minutos).
	 */
	public static function minutos_votacion(): int {
		return (int) apply_filters( 'ed_mvp_votacion_minutos', 10 );
	}

	public static function register_hooks(): void {
		add_action( 'ed_abrir_votacion_mvp', array( self::class, 'abrir_votacion' ), 10, 1 );
		add_action( 'ed_cerrar_votacion_mvp', array( self::class, 'cerrar_votacion' ), 10, 1 );
	}

	public static function abrir_votacion( int $partido_id ): void {
		$cierre = gmdate( 'Y-m-d H:i:s', time() + self::minutos_votacion() * MINUTE_IN_SECONDS );
		update_post_meta( $partido_id, self::META_ABIERTA, '1' );
		update_post_meta( $partido_id, self::META_CIERRE, $cierre );
		if ( class_exists( 'ED_Push' ) ) {
			ED_Push::notificar_apertura_mvp( $partido_id );
		}
		wp_schedule_single_event(
			time() + self::minutos_votacion() * MINUTE_IN_SECONDS,
			'ed_cerrar_votacion_mvp',
			array( $partido_id )
		);
	}

	public static function cerrar_votacion( int $partido_id ): void {
		update_post_meta( $partido_id, self::META_ABIERTA, '0' );
		$ganador = self::get_ganador( $partido_id );
		if ( $ganador && function_exists( 'update_field' ) ) {
			update_field( ED_Torneos::PAR_MVP, (int) $ganador->jugador_id, $partido_id );
			ED_Rankings::incrementar( (int) $ganador->jugador_id, 'mvp', $partido_id );
			$nombre = trim( (string) get_field( 'ed_jugador_nombre', (int) $ganador->jugador_id ) . ' ' . (string) get_field( 'ed_jugador_apellidos', (int) $ganador->jugador_id ) );
			if ( class_exists( 'ED_Push' ) ) {
				ED_Push::enviar(
					__( '🏆 MVP del partit', 'escuela-deportiva-core' ),
					sprintf(
						/* translators: 1: player name, 2: vote count */
						__( 'El MVP és %1$s amb %2$d vots.', 'escuela-deportiva-core' ),
						$nombre,
						(int) $ganador->votos
					),
					array(
						array(
							'field'    => 'tag',
							'key'      => 'partido_' . $partido_id,
							'relation' => '=',
							'value'    => '1',
						),
					),
					ED_Torneos::get_url_partido( $partido_id )
				);
			}
		}
		do_action( 'ed_mvp_cerrado', $partido_id, $ganador ? (int) $ganador->jugador_id : null );
	}

	/**
	 * @return array{ok:bool, error?:string, votos?: array}
	 */
	public static function votar( int $partido_id, int $jugador_id, ?int $user_id, string $fingerprint ): array {
		if ( ! self::esta_abierta( $partido_id ) ) {
			return array( 'ok' => false, 'error' => __( 'La votació no està activa.', 'escuela-deportiva-core' ) );
		}
		if ( ! self::jugador_es_del_partido( $jugador_id, $partido_id ) ) {
			return array( 'ok' => false, 'error' => __( 'Jugador no vàlid per a aquest partit.', 'escuela-deportiva-core' ) );
		}

		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_votos_mvp';

		if ( $user_id ) {
			$existe = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$tabla} WHERE user_id = %d AND partido_id = %d",
					$user_id,
					$partido_id
				)
			);
		} else {
			$existe = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$tabla} WHERE fingerprint = %s AND partido_id = %d",
					$fingerprint,
					$partido_id
				)
			);
		}

		if ( $existe ) {
			return array( 'ok' => false, 'error' => __( 'Ja has votat en aquest partit.', 'escuela-deportiva-core' ) );
		}

		$wpdb->insert(
			$tabla,
			array(
				'partido_id'  => $partido_id,
				'jugador_id'  => $jugador_id,
				'user_id'     => $user_id,
				'fingerprint' => $fingerprint ? $fingerprint : null,
			),
			array( '%d', '%d', '%d', '%s' )
		);

		return array(
			'ok'    => true,
			'votos' => self::get_resultados( $partido_id ),
		);
	}

	public static function esta_abierta( int $partido_id ): bool {
		$abierta = get_post_meta( $partido_id, self::META_ABIERTA, true );
		if ( '1' !== $abierta ) {
			return false;
		}
		$cierre = get_post_meta( $partido_id, self::META_CIERRE, true );
		return (bool) ( $cierre && strtotime( $cierre ) > time() );
	}

	/**
	 * @return array<int, array{jugador_id:int,nombre:string,foto:?string,votos:int,porcentaje:int}>
	 */
	public static function get_resultados( int $partido_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.jugador_id, COUNT(*) AS votos
				FROM {$wpdb->prefix}ed_votos_mvp v
				WHERE v.partido_id = %d
				GROUP BY v.jugador_id
				ORDER BY votos DESC",
				$partido_id
			)
		);
		$total = 0;
		foreach ( $rows ? $rows : array() as $r ) {
			$total += (int) $r->votos;
		}
		$out = array();
		foreach ( $rows ? $rows : array() as $r ) {
			$jid     = (int) $r->jugador_id;
			$foto_id = function_exists( 'get_field' ) ? get_field( 'ed_jugador_foto', $jid ) : null;
			$nombre  = '';
			if ( function_exists( 'get_field' ) ) {
				$nombre = trim( (string) get_field( 'ed_jugador_nombre', $jid ) . ' ' . (string) get_field( 'ed_jugador_apellidos', $jid ) );
			}
			if ( '' === $nombre ) {
				$nombre = get_post_field( 'post_title', $jid );
			}
			$v = (int) $r->votos;
			$out[] = array(
				'jugador_id' => $jid,
				'nombre'     => $nombre,
				'foto'       => $foto_id ? wp_get_attachment_image_url( (int) $foto_id, 'thumbnail' ) : null,
				'votos'      => $v,
				'porcentaje' => $total > 0 ? (int) round( ( $v / $total ) * 100 ) : 0,
			);
		}
		return $out;
	}

	/**
	 * @return array{abierta:bool, segundos_resto:int, resultados: array, mvp_asignado: int, candidatos: array}
	 */
	public static function get_estado_votacion( int $partido_id ): array {
		$abierta = self::esta_abierta( $partido_id );
		$cierre  = get_post_meta( $partido_id, self::META_CIERRE, true );
		$res     = self::get_resultados( $partido_id );
		$cand    = ED_Partidos_Eventos::get_candidatos_mvp( $partido_id );
		return array(
			'abierta'        => $abierta,
			'segundos_resto' => $abierta && $cierre ? max( 0, (int) strtotime( $cierre ) - time() ) : 0,
			'resultados'     => $res,
			'mvp_asignado'   => function_exists( 'get_field' ) ? (int) get_field( ED_Torneos::PAR_MVP, $partido_id, false ) : 0,
			'candidatos'     => $cand,
		);
	}

	private static function get_ganador( int $partido_id ): ?object {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT jugador_id, COUNT(*) AS votos
				FROM {$wpdb->prefix}ed_votos_mvp
				WHERE partido_id = %d
				GROUP BY jugador_id
				ORDER BY votos DESC
				LIMIT 1",
				$partido_id
			)
		);
		return $row ?: null;
	}

	private static function jugador_es_del_partido( int $jugador_id, int $partido_id ): bool {
		if ( ! function_exists( 'get_field' ) ) {
			return false;
		}
		$local_id     = (int) get_field( ED_Torneos::PAR_EQ_LOCAL, $partido_id, false );
		$visitante_id = (int) get_field( ED_Torneos::PAR_EQ_VISITANTE, $partido_id, false );
		$eq_jug       = (int) get_field( 'ed_jugador_equipo', $jugador_id, false );
		return in_array( $eq_jug, array( $local_id, $visitante_id ), true );
	}

	public static function generar_fingerprint(): string {
		$ip = isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) : '';
		if ( '' === $ip ) {
			$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		}
		$ua = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['HTTP_USER_AGENT'] ) ) : 'unknown';
		return hash( 'sha256', $ip . '|' . $ua );
	}
}
