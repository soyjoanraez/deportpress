<?php
/**
 * Calendari unificat + resultats setmanals (Fase 8).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fusiona entrenaments, partits de torneig i partits FFCV.
 */
class ED_Calendario {

	/**
	 * Normalitza a `Y-m-d H:i:s` per ordenar.
	 */
	private static function normalizar_fecha_hora( string $fh ): string {
		$fh = trim( $fh );
		if ( '' === $fh ) {
			return '1970-01-01 00:00:00';
		}
		if ( preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$/', $fh ) ) {
			return $fh . ':00';
		}
		return $fh;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_eventos_semana( string $fecha_inicio, string $fecha_fin, ?int $categoria_id = null, ?int $jugador_id = null ): array {
		$eventos = array_merge(
			self::get_entrenamientos( $fecha_inicio, $fecha_fin, $categoria_id ),
			self::get_partidos_torneo( $fecha_inicio, $fecha_fin, $categoria_id ),
			self::get_partidos_fed( $fecha_inicio, $fecha_fin, $categoria_id )
		);

		if ( $jugador_id ) {
			$eventos = self::filtrar_por_jugador( $eventos, $jugador_id );
		}

		usort(
			$eventos,
			static function ( $a, $b ) {
				return strcmp( (string) $a['fecha_hora'], (string) $b['fecha_hora'] );
			}
		);

		return $eventos;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_eventos_mes( int $year, int $month, ?int $categoria_id = null ): array {
		$month = max( 1, min( 12, $month ) );
		$year  = max( 1970, min( 2100, $year ) );
		$ini   = sprintf( '%d-%02d-01', $year, $month );
		$fin   = gmdate( 'Y-m-t', strtotime( $ini . ' 12:00:00' ) );
		return self::get_eventos_semana( $ini, $fin, $categoria_id );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_entrenamientos( string $inicio, string $fin, ?int $categoria_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}

		$meta_query = array(
			array(
				'key'     => 'ed_ent_fecha_hora',
				'value'   => array( $inicio . ' 00:00:00', $fin . ' 23:59:59' ),
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME',
			),
		);

		if ( $categoria_id ) {
			$meta_query[] = array(
				'key'   => 'ed_ent_categoria',
				'value' => $categoria_id,
			);
		}

		$posts = get_posts(
			array(
				'post_type'      => 'entrenamiento',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => $meta_query,
				'orderby'        => 'meta_value',
				'meta_key'       => 'ed_ent_fecha_hora',
				'order'          => 'ASC',
			)
		);

		$out = array();
		foreach ( $posts as $p ) {
			$cat_id = (int) get_field( 'ed_ent_categoria', $p->ID, false );
			$fh_raw = (string) get_field( 'ed_ent_fecha_hora', $p->ID );
			$fh     = self::normalizar_fecha_hora( $fh_raw );
			$out[]  = array(
				'id'           => 'entrenamiento-' . $p->ID,
				'tipo'         => 'entrenamiento',
				'source_id'    => (int) $p->ID,
				'titulo'       => __( 'Entrenament', 'escuela-deportiva-core' ) . ( $cat_id ? ' — ' . get_the_title( $cat_id ) : '' ),
				'fecha_hora'   => $fh,
				'lugar'        => (string) get_field( 'ed_ent_lugar', $p->ID ),
				'categoria_id' => $cat_id,
				'categoria'    => $cat_id ? get_the_title( $cat_id ) : null,
				'color'        => '#1565C0',
				'icono'        => '🏃',
				'url'          => null,
				'marcador'     => '',
				'estado'       => '',
			);
		}
		return $out;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_partidos_torneo( string $inicio, string $fin, ?int $categoria_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}

		$meta_query = array(
			array(
				'key'     => 'ed_par_fecha_hora',
				'value'   => array( $inicio . ' 00:00:00', $fin . ' 23:59:59' ),
				'compare' => 'BETWEEN',
				'type'    => 'DATETIME',
			),
		);

		$posts = get_posts(
			array(
				'post_type'      => 'partido',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => $meta_query,
				'orderby'        => 'meta_value',
				'meta_key'       => 'ed_par_fecha_hora',
				'order'          => 'ASC',
			)
		);

		$resultado = array();
		foreach ( $posts as $p ) {
			$local_id     = (int) get_field( ED_Torneos::PAR_EQ_LOCAL, $p->ID, false );
			$visitante_id = (int) get_field( ED_Torneos::PAR_EQ_VISITANTE, $p->ID, false );
			$torneo_id    = (int) get_field( ED_Torneos::PAR_TORNEO, $p->ID, false );
			$estado       = (string) get_field( ED_Torneos::PAR_ESTADO, $p->ID );

			$cat_local = $local_id ? (int) get_field( 'ed_eq_categoria', $local_id, false ) : 0;
			$cat_visit = $visitante_id ? (int) get_field( 'ed_eq_categoria', $visitante_id, false ) : 0;

			if ( $categoria_id && $cat_local !== $categoria_id && $cat_visit !== $categoria_id ) {
				continue;
			}

			$cat_event_id = $cat_local ? $cat_local : $cat_visit;
			$cat_label    = $cat_event_id ? get_the_title( $cat_event_id ) : null;

			$marcador = '';
			if ( in_array( $estado, array( 'finalizado', 'en_curso', 'descanso' ), true ) ) {
				$marcador = (int) get_field( ED_Torneos::PAR_GOLES_LOCAL, $p->ID ) . ' - ' . (int) get_field( ED_Torneos::PAR_GOLES_VIS, $p->ID );
			}

			$fh_raw = (string) get_field( ED_Torneos::PAR_FECHA_HORA, $p->ID );
			$fh     = self::normalizar_fecha_hora( $fh_raw );

			$resultado[] = array(
				'id'           => 'partido-' . $p->ID,
				'tipo'         => 'partido_torneo',
				'source_id'    => (int) $p->ID,
				'titulo'       => get_the_title( $local_id ) . ' vs ' . get_the_title( $visitante_id ),
				'fecha_hora'   => $fh,
				'lugar'        => (string) get_field( ED_Torneos::PAR_LUGAR, $p->ID ),
				'estado'       => $estado,
				'marcador'     => $marcador,
				'torneo'       => $torneo_id ? get_the_title( $torneo_id ) : null,
				'categoria_id' => $cat_event_id,
				'categoria'    => $cat_label,
				'url'          => ED_Torneos::get_url_partido( $p->ID ),
				'color'        => '#E65100',
				'icono'        => '⚽',
			);
		}

		return $resultado;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function get_partidos_fed( string $inicio, string $fin, ?int $categoria_id ): array {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_fed_partidos';

		$sql    = "SELECT * FROM {$tabla} WHERE fecha_hora BETWEEN %s AND %s AND es_nuestro_equipo = 1";
		$params = array( $inicio . ' 00:00:00', $fin . ' 23:59:59' );

		if ( $categoria_id ) {
			$sql     .= ' AND categoria_id = %d';
			$params[] = $categoria_id;
		}

		$sql .= ' ORDER BY fecha_hora ASC';

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $r ) {
			$marcador = '';
			if ( 'jugado' === $r->estado && null !== $r->goles_local && null !== $r->goles_visitante ) {
				$marcador = (int) $r->goles_local . ' - ' . (int) $r->goles_visitante;
			}
			$fh = self::normalizar_fecha_hora( (string) $r->fecha_hora );

			$out[] = array(
				'id'           => 'fed-' . $r->id,
				'tipo'         => 'partido_liga',
				'source_id'    => (int) $r->id,
				'titulo'       => $r->equipo_local . ' vs ' . $r->equipo_visitante,
				'fecha_hora'   => $fh,
				'lugar'        => $r->campo ? (string) $r->campo : '',
				'estado'       => (string) $r->estado,
				'marcador'     => $marcador,
				'categoria'    => $r->categoria_ffcv ? (string) $r->categoria_ffcv : null,
				'categoria_id' => isset( $r->categoria_id ) ? (int) $r->categoria_id : null,
				'jornada'      => isset( $r->jornada ) ? (int) $r->jornada : null,
				'color'        => '#2E7D32',
				'icono'        => '🏆',
				'url'          => null,
			);
		}
		return $out;
	}

	/**
	 * @param array<int, array<string, mixed>> $eventos
	 * @return array<int, array<string, mixed>>
	 */
	private static function filtrar_por_jugador( array $eventos, int $jugador_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return $eventos;
		}
		$categoria_id = (int) get_field( 'ed_jugador_categoria', $jugador_id, false );
		if ( ! $categoria_id ) {
			return $eventos;
		}
		return array_values(
			array_filter(
				$eventos,
				static function ( $e ) use ( $categoria_id ) {
					if ( ! isset( $e['categoria_id'] ) ) {
						return true;
					}
					return (int) $e['categoria_id'] === $categoria_id;
				}
			)
		);
	}

	/**
	 * @return array{semana_inicio:string,semana_fin:string,categorias:array<string, array<int, array<string, mixed>>>,total:int}
	 */
	public static function get_resultados_semana( ?string $fecha_ref = null ): array {
		$tz  = wp_timezone();
		$ref = $fecha_ref ? $fecha_ref : wp_date( 'Y-m-d' );
		try {
			$d     = new DateTimeImmutable( $ref . ' 12:00:00', $tz );
			$lunes = $d->modify( 'monday this week' );
		} catch ( \Exception $e ) {
			$d     = new DateTimeImmutable( 'now', $tz );
			$lunes = $d->modify( 'monday this week' );
		}
		$domingo = $lunes->modify( '+6 days' );

		$ini = $lunes->format( 'Y-m-d' );
		$fin = $domingo->format( 'Y-m-d' );

		$todos_eventos = self::get_eventos_semana( $ini, $fin );

		$jugados = array_filter(
			$todos_eventos,
			static function ( $e ) {
				$tipo   = $e['tipo'] ?? '';
				$estado = (string) ( $e['estado'] ?? '' );
				$marc   = (string) ( $e['marcador'] ?? '' );
				return in_array( $tipo, array( 'partido_torneo', 'partido_liga' ), true )
					&& in_array( $estado, array( 'finalizado', 'jugado' ), true )
					&& '' !== $marc;
			}
		);

		$por_categoria = array();
		foreach ( $jugados as $p ) {
			$cat = $p['categoria'] ?? $p['torneo'] ?? __( 'Sense categoria', 'escuela-deportiva-core' );
			if ( ! isset( $por_categoria[ $cat ] ) ) {
				$por_categoria[ $cat ] = array();
			}
			$por_categoria[ $cat ][] = $p;
		}

		return array(
			'semana_inicio' => $ini,
			'semana_fin'    => $fin,
			'categorias'    => $por_categoria,
			'total'         => count( $jugados ),
		);
	}
}
