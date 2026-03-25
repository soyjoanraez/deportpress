<?php
/**
 * Persistència d’assistències (taula ed_asistencias).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * CRUD assistències.
 */
class ED_Asistencias {

	/**
	 * Desa o actualitza un registre.
	 */
	public static function guardar(
		int $jugador_id,
		int $sesion_id,
		string $tipo_sesion,
		string $estado,
		string $motivo = '',
		string $fecha = ''
	): bool {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_asistencias';

		if ( '' === $fecha ) {
			$fecha = gmdate( 'Y-m-d' );
		}

		$existente = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$tabla} WHERE jugador_id = %d AND sesion_id = %d AND tipo_sesion = %s",
				$jugador_id,
				$sesion_id,
				$tipo_sesion
			)
		);

		$data = array(
			'jugador_id'  => $jugador_id,
			'sesion_id'   => $sesion_id,
			'tipo_sesion' => $tipo_sesion,
			'estado'      => $estado,
			'motivo'      => sanitize_text_field( $motivo ),
			'fecha'       => $fecha,
		);

		if ( $existente ) {
			return (bool) $wpdb->update(
				$tabla,
				$data,
				array( 'id' => (int) $existente ),
				array( '%d', '%d', '%s', '%s', '%s', '%s' ),
				array( '%d' )
			);
		}

		$data['creado_en'] = current_time( 'mysql' );
		return (bool) $wpdb->insert(
			$tabla,
			$data,
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Desa la llista d’una sessió.
	 *
	 * @param array<int, array{jugador_id:int, estado:string, motivo?:string}> $registros
	 */
	public static function guardar_sesion(
		int $sesion_id,
		string $tipo_sesion,
		string $fecha,
		array $registros
	): void {
		foreach ( $registros as $r ) {
			self::guardar(
				(int) $r['jugador_id'],
				$sesion_id,
				$tipo_sesion,
				sanitize_text_field( $r['estado'] ),
				isset( $r['motivo'] ) ? (string) $r['motivo'] : '',
				$fecha
			);
		}

		if ( 'entrenamiento' === $tipo_sesion && function_exists( 'update_field' ) ) {
			update_field( 'ed_ent_asistencia_cerrada', true, $sesion_id );
		}
	}

	/**
	 * @return object[]
	 */
	public static function get_por_jugador( int $jugador_id, int $limit = 30 ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, p.post_title AS sesion_nombre
				FROM {$wpdb->prefix}ed_asistencias a
				LEFT JOIN {$wpdb->posts} p ON p.ID = a.sesion_id
				WHERE a.jugador_id = %d
				ORDER BY a.fecha DESC
				LIMIT %d",
				$jugador_id,
				$limit
			)
		);
	}

	/**
	 * @return object[]
	 */
	public static function get_por_sesion( int $sesion_id, string $tipo ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.*, p.post_title AS jugador_nombre
				FROM {$wpdb->prefix}ed_asistencias a
				LEFT JOIN {$wpdb->posts} p ON p.ID = a.jugador_id
				WHERE a.sesion_id = %d AND a.tipo_sesion = %s
				ORDER BY p.post_title ASC",
				$sesion_id,
				$tipo
			)
		);
	}

	/**
	 * Mapa de assistència per jugador dins d'una sessió.
	 *
	 * @param int[] $jugador_ids IDs de jugadors a consultar.
	 * @return array<int, array{estado:string,motivo:string}>
	 */
	public static function get_mapa_sesion( int $sesion_id, string $tipo, array $jugador_ids = array() ): array {
		global $wpdb;

		$tabla      = $wpdb->prefix . 'ed_asistencias';
		$jugador_ids = array_values( array_filter( array_map( 'intval', $jugador_ids ) ) );
		$params     = array( $sesion_id, $tipo );
		$sql        = "SELECT jugador_id, estado, motivo FROM {$tabla} WHERE sesion_id = %d AND tipo_sesion = %s";

		if ( array() !== $jugador_ids ) {
			$placeholders = implode( ',', array_fill( 0, count( $jugador_ids ), '%d' ) );
			$sql         .= " AND jugador_id IN ({$placeholders})";
			$params       = array_merge( $params, $jugador_ids );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $row ) {
			$out[ (int) $row->jugador_id ] = array(
				'estado' => (string) $row->estado,
				'motivo' => (string) ( $row->motivo ?? '' ),
			);
		}

		return $out;
	}

	public static function get_porcentaje( int $jugador_id, string $tipo = 'entrenamiento' ): float {
		global $wpdb;

		$total = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ed_asistencias
				WHERE jugador_id = %d AND tipo_sesion = %s",
				$jugador_id,
				$tipo
			)
		);

		if ( $total <= 0 ) {
			return 0.0;
		}

		$asistidas = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ed_asistencias
				WHERE jugador_id = %d AND tipo_sesion = %s AND estado = %s",
				$jugador_id,
				$tipo,
				'asistio'
			)
		);

		return round( ( $asistidas / $total ) * 100, 1 );
	}
}
