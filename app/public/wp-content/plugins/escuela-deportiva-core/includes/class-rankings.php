<?php
/**
 * Rankings en caché (taula ed_rankings).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stats agregades per jugador / torneig.
 */
class ED_Rankings {

	/**
	 * @param string $tipo goles|mvp|amarillas|rojas
	 */
	public static function incrementar( int $jugador_id, string $tipo, int $partido_id ): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$torneo_id = (int) get_field( ED_Torneos::PAR_TORNEO, $partido_id, false );
		self::upsert( $jugador_id, $tipo, null );
		if ( $torneo_id > 0 ) {
			self::upsert( $jugador_id, $tipo, $torneo_id );
		}
		delete_transient( 'ed_ranking_' . $tipo . '_torneo_' . $torneo_id );
		delete_transient( 'ed_ranking_' . $tipo . '_global' );
	}

	private static function upsert( int $jugador_id, string $tipo, ?int $torneo_id ): void {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_rankings';

		if ( null === $torneo_id ) {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$tabla} WHERE jugador_id = %d AND tipo = %s AND torneo_id IS NULL",
					$jugador_id,
					$tipo
				)
			);
		} else {
			$existing = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$tabla} WHERE jugador_id = %d AND tipo = %s AND torneo_id = %d",
					$jugador_id,
					$tipo,
					$torneo_id
				)
			);
		}

		if ( $existing ) {
			$wpdb->query( $wpdb->prepare( "UPDATE {$tabla} SET valor = valor + 1 WHERE id = %d", (int) $existing ) );
		} else {
			$wpdb->insert(
				$tabla,
				array(
					'jugador_id' => $jugador_id,
					'tipo'       => $tipo,
					'torneo_id'  => $torneo_id,
					'valor'      => 1,
				),
				array( '%d', '%s', '%d', '%d' )
			);
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_ranking( string $tipo, ?int $torneo_id = null, int $limit = 10 ): array {
		$cache_key = 'ed_ranking_' . $tipo . '_' . ( $torneo_id ? 'torneo_' . $torneo_id : 'global' );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_rankings';
		if ( null === $torneo_id ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.jugador_id, r.valor, p.post_title AS jugador_nombre
					FROM {$tabla} r
					LEFT JOIN {$wpdb->posts} p ON p.ID = r.jugador_id
					WHERE r.tipo = %s AND r.torneo_id IS NULL
					ORDER BY r.valor DESC
					LIMIT %d",
					$tipo,
					$limit
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT r.jugador_id, r.valor, p.post_title AS jugador_nombre
					FROM {$tabla} r
					LEFT JOIN {$wpdb->posts} p ON p.ID = r.jugador_id
					WHERE r.tipo = %s AND r.torneo_id = %d
					ORDER BY r.valor DESC
					LIMIT %d",
					$tipo,
					$torneo_id,
					$limit
				)
			);
		}

		$resultado = array();
		foreach ( $rows ? $rows : array() as $r ) {
			$jid     = (int) $r->jugador_id;
			$foto_id = function_exists( 'get_field' ) ? get_field( 'ed_jugador_foto', $jid ) : null;
			$eq_id   = function_exists( 'get_field' ) ? (int) get_field( 'ed_jugador_equipo', $jid, false ) : 0;
			$esc_id  = ( $eq_id && function_exists( 'get_field' ) ) ? get_field( 'ed_eq_escudo', $eq_id ) : null;
			$nombre  = '';
			if ( function_exists( 'get_field' ) ) {
				$nombre = trim( (string) get_field( 'ed_jugador_nombre', $jid ) . ' ' . (string) get_field( 'ed_jugador_apellidos', $jid ) );
			}
			if ( '' === $nombre ) {
				$nombre = (string) $r->jugador_nombre;
			}
			$resultado[] = array(
				'jugador_id'    => $jid,
				'nombre'        => $nombre,
				'foto'          => $foto_id ? wp_get_attachment_image_url( (int) $foto_id, 'thumbnail' ) : null,
				'equipo'        => $eq_id ? get_post_field( 'post_title', $eq_id ) : null,
				'escudo_equipo' => $esc_id ? wp_get_attachment_image_url( (int) $esc_id, 'thumbnail' ) : null,
				'valor'         => (int) $r->valor,
			);
		}

		set_transient( $cache_key, $resultado, 5 * MINUTE_IN_SECONDS );
		return $resultado;
	}

	/**
	 * @return array{goles:int,mvp:int,amarillas:int,rojas:int}
	 */
	public static function get_stats_jugador( int $jugador_id, ?int $torneo_id = null ): array {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_rankings';
		if ( null === $torneo_id ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tipo, valor FROM {$tabla} WHERE jugador_id = %d AND torneo_id IS NULL",
					$jugador_id
				)
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT tipo, valor FROM {$tabla} WHERE jugador_id = %d AND torneo_id = %d",
					$jugador_id,
					$torneo_id
				)
			);
		}
		$stats = array(
			'goles'     => 0,
			'mvp'       => 0,
			'amarillas' => 0,
			'rojas'     => 0,
		);
		foreach ( $rows ? $rows : array() as $row ) {
			$t = (string) $row->tipo;
			if ( isset( $stats[ $t ] ) ) {
				$stats[ $t ] = (int) $row->valor;
			}
		}
		return $stats;
	}

	/**
	 * Recalcular goles i MVP des de zero (reparació).
	 */
	public static function recalcular_todo( ?int $torneo_id = null ): void {
		global $wpdb;
		$tabla     = $wpdb->prefix . 'ed_rankings';
		$mk_torneo = ED_Torneos::PAR_TORNEO;
		$mk_mvp    = ED_Torneos::PAR_MVP;

		if ( $torneo_id ) {
			$wpdb->delete( $tabla, array( 'torneo_id' => $torneo_id ), array( '%d' ) );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "TRUNCATE TABLE {$tabla}" );
		}

		if ( $torneo_id ) {
			$goles = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT e.jugador_id, COUNT(*) AS total
					FROM {$wpdb->prefix}ed_eventos_partido e
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = e.partido_id AND pm.meta_key = %s AND pm.meta_value = %s
					WHERE e.tipo = 'gol' AND e.jugador_id IS NOT NULL
					GROUP BY e.jugador_id",
					$mk_torneo,
					(string) $torneo_id
				)
			);
			foreach ( $goles ? $goles : array() as $r ) {
				$wpdb->insert(
					$tabla,
					array(
						'jugador_id' => (int) $r->jugador_id,
						'tipo'       => 'goles',
						'torneo_id'  => $torneo_id,
						'valor'      => (int) $r->total,
					),
					array( '%d', '%s', '%d', '%d' )
				);
			}
			$mvps = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT CAST(pj.meta_value AS UNSIGNED) AS jugador_id, COUNT(*) AS total
					FROM {$wpdb->postmeta} pj
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = pj.post_id AND pm.meta_key = %s AND pm.meta_value = %s
					WHERE pj.meta_key = %s AND pj.meta_value != '' AND pj.meta_value != '0'
					GROUP BY pj.meta_value",
					$mk_torneo,
					(string) $torneo_id,
					$mk_mvp
				)
			);
			foreach ( $mvps ? $mvps : array() as $r ) {
				$wpdb->insert(
					$tabla,
					array(
						'jugador_id' => (int) $r->jugador_id,
						'tipo'       => 'mvp',
						'torneo_id'  => $torneo_id,
						'valor'      => (int) $r->total,
					),
					array( '%d', '%s', '%d', '%d' )
				);
			}
		} else {
			$goles_torneo = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT e.jugador_id, CAST(pm.meta_value AS UNSIGNED) AS tid, COUNT(*) AS total
					FROM {$wpdb->prefix}ed_eventos_partido e
					INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = e.partido_id AND pm.meta_key = %s
					WHERE e.tipo = 'gol' AND e.jugador_id IS NOT NULL AND pm.meta_value != ''
					GROUP BY e.jugador_id, pm.meta_value",
					$mk_torneo
				)
			);
			foreach ( $goles_torneo ? $goles_torneo : array() as $r ) {
				$tid = (int) $r->tid;
				if ( $tid <= 0 ) {
					continue;
				}
				$wpdb->insert(
					$tabla,
					array(
						'jugador_id' => (int) $r->jugador_id,
						'tipo'       => 'goles',
						'torneo_id'  => $tid,
						'valor'      => (int) $r->total,
					),
					array( '%d', '%s', '%d', '%d' )
				);
			}
			$goles_glob = $wpdb->get_results(
				"SELECT e.jugador_id, COUNT(*) AS total
				FROM {$wpdb->prefix}ed_eventos_partido e
				WHERE e.tipo = 'gol' AND e.jugador_id IS NOT NULL
				GROUP BY e.jugador_id"
			);
			foreach ( $goles_glob ? $goles_glob : array() as $r ) {
				$wpdb->insert(
					$tabla,
					array(
						'jugador_id' => (int) $r->jugador_id,
						'tipo'       => 'goles',
						'torneo_id'  => null,
						'valor'      => (int) $r->total,
					),
					array( '%d', '%s', '%d', '%d' )
				);
			}
		}

		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_ed_ranking_%' OR option_name LIKE '_transient_timeout_ed_ranking_%'" );
	}
}
