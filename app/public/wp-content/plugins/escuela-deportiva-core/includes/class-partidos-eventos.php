<?php
/**
 * Esdeveniments de partit en viu (taula ed_eventos_partido).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Escriu esdeveniments i dispara ed_evento_partido_registrado.
 */
class ED_Partidos_Eventos {

	/**
	 * Registra esdeveniment i actualitza marcador/estat del partit.
	 *
	 * @return int|false ID de fila o false.
	 */
	public static function registrar(
		int $partido_id,
		string $tipo,
		?int $jugador_id = null,
		?int $equipo_id = null,
		?int $minuto = null,
		string $descripcion = ''
	): int|false {
		global $wpdb;
		if ( get_post_type( $partido_id ) !== 'partido' ) {
			return false;
		}

		$tipo = sanitize_key( $tipo );
		$now  = current_time( 'mysql' );

		$result = $wpdb->insert(
			$wpdb->prefix . 'ed_eventos_partido',
			array(
				'partido_id'  => $partido_id,
				'tipo'        => $tipo,
				'jugador_id'  => $jugador_id,
				'equipo_id'   => $equipo_id,
				'minuto'      => $minuto,
				'descripcion' => mb_substr( sanitize_text_field( $descripcion ), 0, 500 ),
				'creado_en'   => $now,
			),
			array( '%d', '%s', '%d', '%d', '%d', '%s', '%s' )
		);

		if ( ! $result ) {
			return false;
		}

		$evento_id = (int) $wpdb->insert_id;

		if ( 'gol' === $tipo && $equipo_id ) {
			self::actualizar_marcador( $partido_id, $equipo_id );
		}

		$nuevo_estado = match ( $tipo ) {
			'inicio' => 'en_curso',
			'fin_primera' => 'descanso',
			'fin_partido' => 'finalizado',
			default => null,
		};
		if ( $nuevo_estado && function_exists( 'update_field' ) ) {
			update_field( ED_Torneos::PAR_ESTADO, $nuevo_estado, $partido_id );
		}

		if ( null !== $minuto && function_exists( 'update_field' ) ) {
			update_field( ED_Torneos::PAR_MINUTO, (int) $minuto, $partido_id );
		}

		do_action( 'ed_evento_partido_registrado', $evento_id, $partido_id, $tipo, $jugador_id, $equipo_id, $minuto );

		return $evento_id;
	}

	private static function actualizar_marcador( int $partido_id, int $equipo_id ): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$local_id     = (int) get_field( ED_Torneos::PAR_EQ_LOCAL, $partido_id, false );
		$visitante_id = (int) get_field( ED_Torneos::PAR_EQ_VISITANTE, $partido_id, false );
		if ( $equipo_id === $local_id ) {
			$goles = (int) get_field( ED_Torneos::PAR_GOLES_LOCAL, $partido_id );
			update_field( ED_Torneos::PAR_GOLES_LOCAL, $goles + 1, $partido_id );
		} elseif ( $equipo_id === $visitante_id ) {
			$goles = (int) get_field( ED_Torneos::PAR_GOLES_VIS, $partido_id );
			update_field( ED_Torneos::PAR_GOLES_VIS, $goles + 1, $partido_id );
		}
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_eventos( int $partido_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.*, p.post_title AS jugador_nombre, eq.post_title AS equipo_nombre
				FROM {$wpdb->prefix}ed_eventos_partido e
				LEFT JOIN {$wpdb->posts} p ON p.ID = e.jugador_id
				LEFT JOIN {$wpdb->posts} eq ON eq.ID = e.equipo_id
				WHERE e.partido_id = %d
				ORDER BY e.id ASC",
				$partido_id
			)
		);
		return array_map( array( self::class, 'format_evento' ), $rows ? $rows : array() );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_eventos_desde( int $partido_id, int $desde_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.*, p.post_title AS jugador_nombre, eq.post_title AS equipo_nombre
				FROM {$wpdb->prefix}ed_eventos_partido e
				LEFT JOIN {$wpdb->posts} p ON p.ID = e.jugador_id
				LEFT JOIN {$wpdb->posts} eq ON eq.ID = e.equipo_id
				WHERE e.partido_id = %d AND e.id > %d
				ORDER BY e.id ASC",
				$partido_id,
				$desde_id
			)
		);
		return array_map( array( self::class, 'format_evento' ), $rows ? $rows : array() );
	}

	/**
	 * @return array{partido: array<string, mixed>, eventos: array, ultimo_id: int}
	 */
	public static function get_partido_live( int $partido_id ): array {
		$partido   = ED_Torneos::format_partido( $partido_id );
		$eventos   = self::get_eventos( $partido_id );
		$ultimo_id = 0;
		foreach ( $eventos as $e ) {
			if ( isset( $e['id'] ) && (int) $e['id'] > $ultimo_id ) {
				$ultimo_id = (int) $e['id'];
			}
		}
		return array(
			'partido'   => $partido,
			'eventos'   => $eventos,
			'ultimo_id' => $ultimo_id,
		);
	}

	/**
	 * Plantilles local / visitant per al panell operador.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_jugadores_partido( int $partido_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}
		$local_id     = (int) get_field( ED_Torneos::PAR_EQ_LOCAL, $partido_id, false );
		$visitante_id = (int) get_field( ED_Torneos::PAR_EQ_VISITANTE, $partido_id, false );
		$result       = array();
		foreach ( array( 'local' => $local_id, 'visitante' => $visitante_id ) as $rol => $equipo_id ) {
			if ( $equipo_id <= 0 ) {
				continue;
			}
			$jugadores = get_posts(
				array(
					'post_type'      => 'jugador',
					'posts_per_page' => -1,
					'post_status'    => 'publish',
					'meta_key'       => 'ed_jugador_equipo',
					'meta_value'     => $equipo_id,
					'orderby'        => 'title',
					'order'          => 'ASC',
				)
			);
			$result[ $rol ] = array(
				'equipo_id'     => $equipo_id,
				'equipo_nombre' => get_post_field( 'post_title', $equipo_id ),
				'jugadores'     => array_map(
					function ( $j ) {
						$fid = get_field( 'ed_jugador_foto', $j->ID );
						$url = $fid ? wp_get_attachment_image_url( (int) $fid, 'thumbnail' ) : '';
						return array(
							'id'        => $j->ID,
							'nombre'    => trim( (string) get_field( 'ed_jugador_nombre', $j->ID ) . ' ' . (string) get_field( 'ed_jugador_apellidos', $j->ID ) ),
							'dorsal'    => (int) get_field( 'ed_jugador_dorsal', $j->ID ),
							'foto'      => $url ? $url : '',
							'equipo_id' => (int) get_field( 'ed_jugador_equipo', $j->ID, false ),
						);
					},
					$jugadores
				),
			);
		}
		return $result;
	}

	/**
	 * Llista plana de candidats MVP (dos equips del partit).
	 *
	 * @return array<int, array{jugador_id:int,nombre:string,foto:?string,equipo:string}>
	 */
	public static function get_candidatos_mvp( int $partido_id ): array {
		$plant = self::get_jugadores_partido( $partido_id );
		$out   = array();
		foreach ( $plant as $bloque ) {
			if ( empty( $bloque['jugadores'] ) || ! is_array( $bloque['jugadores'] ) ) {
				continue;
			}
			$eq_name = (string) ( $bloque['equipo_nombre'] ?? '' );
			foreach ( $bloque['jugadores'] as $j ) {
				$out[] = array(
					'jugador_id' => (int) $j['id'],
					'nombre'     => (string) $j['nombre'],
					'foto'       => $j['foto'] ? (string) $j['foto'] : null,
					'equipo'     => $eq_name,
				);
			}
		}
		return $out;
	}

	/**
	 * @param object $row DB row.
	 * @return array<string, mixed>
	 */
	private static function format_evento( object $row ): array {
		return array(
			'id'             => (int) $row->id,
			'tipo'           => (string) $row->tipo,
			'minuto'         => isset( $row->minuto ) && null !== $row->minuto ? (int) $row->minuto : null,
			'jugador_id'     => $row->jugador_id ? (int) $row->jugador_id : null,
			'jugador_nombre' => isset( $row->jugador_nombre ) ? (string) $row->jugador_nombre : '',
			'equipo_id'      => isset( $row->equipo_id ) && $row->equipo_id ? (int) $row->equipo_id : null,
			'equipo_nombre'  => isset( $row->equipo_nombre ) ? (string) $row->equipo_nombre : '',
			'descripcion'    => isset( $row->descripcion ) ? (string) $row->descripcion : '',
			'creado_en'      => (string) $row->creado_en,
		);
	}

	/**
	 * @return object[]
	 */
	public static function get_goleadores( int $torneo_id, int $limit = 10 ): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name.
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.jugador_id, p.post_title AS jugador_nombre, COUNT(*) AS goles
				FROM {$wpdb->prefix}ed_eventos_partido e
				INNER JOIN {$wpdb->posts} par ON par.ID = e.partido_id AND par.post_type = 'partido'
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = e.partido_id AND pm.meta_key = %s AND pm.meta_value = %s
				INNER JOIN {$wpdb->posts} p ON p.ID = e.jugador_id
				WHERE e.tipo = 'gol' AND e.jugador_id IS NOT NULL
				GROUP BY e.jugador_id
				ORDER BY goles DESC
				LIMIT %d",
				ED_Torneos::PAR_TORNEO,
				(string) $torneo_id,
				$limit
			)
		);
	}

	/**
	 * @return object[]
	 */
	public static function get_tarjetas( int $torneo_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.jugador_id, p.post_title AS jugador_nombre, e.tipo, COUNT(*) AS total
				FROM {$wpdb->prefix}ed_eventos_partido e
				INNER JOIN {$wpdb->posts} p ON p.ID = e.jugador_id
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = e.partido_id AND pm.meta_key = %s AND pm.meta_value = %s
				WHERE e.tipo IN ('tarjeta_amarilla','tarjeta_roja') AND e.jugador_id IS NOT NULL
				GROUP BY e.jugador_id, e.tipo
				ORDER BY total DESC",
				ED_Torneos::PAR_TORNEO,
				(string) $torneo_id
			)
		);
	}
}
