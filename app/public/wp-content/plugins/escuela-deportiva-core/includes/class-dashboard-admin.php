<?php
/**
 * Mètriques i exportació CSV per al dashboard d’administració (Fase 10).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Dades agregades amb transients.
 */
class ED_Dashboard_Admin {

	private const CACHE_TTL = 600;

	/**
	 * @return array<string, mixed>
	 */
	public static function get_kpis(): array {
		$cache_key = 'ed_dashboard_kpis_v1';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		$jugadores_activos = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
			INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
			WHERE p.post_type = 'jugador' AND p.post_status = 'publish'
			AND pm.meta_key = 'ed_jugador_estado' AND pm.meta_value = 'activo'"
		);

		$mes_inicio = wp_date( 'Y-m-01 00:00:00' );
		$mes_fin    = wp_date( 'Y-m-t 23:59:59' );

		$tabla_pagos = $wpdb->prefix . 'ed_pagos_plazos';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ingresos_mes = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(importe), 0) FROM {$tabla_pagos}
				WHERE estado = 'pagado'
				AND fecha_pagado IS NOT NULL
				AND fecha_pagado BETWEEN %s AND %s",
				$mes_inicio,
				$mes_fin
			)
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pagos = $wpdb->get_row(
			"SELECT
				COUNT(*) AS total,
				SUM(estado = 'pagado') AS pagados,
				SUM(estado = 'pendiente') AS pendientes,
				SUM(estado = 'fallido') AS fallidos,
				SUM(estado = 'cancelado') AS cancelados
			FROM {$tabla_pagos}"
		);

		$total_plazos = (int) ( $pagos->total ?? 0 );
		$pagados_n    = (int) ( $pagos->pagados ?? 0 );
		$pct_pagados  = $total_plazos > 0 ? round( ( $pagados_n / $total_plazos ) * 100, 1 ) : 0.0;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ingresos_temporada = (float) $wpdb->get_var(
			"SELECT COALESCE(SUM(importe), 0) FROM {$tabla_pagos} WHERE estado = 'pagado'"
		);

		$tabla_a   = $wpdb->prefix . 'ed_asistencias';
		$fecha_30d = gmdate( 'Y-m-d', strtotime( '-30 days', (int) current_time( 'timestamp' ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$asistencias = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, SUM(estado = 'asistio') AS asistidas
				FROM {$tabla_a}
				WHERE tipo_sesion = 'entrenamiento' AND fecha >= %s",
				$fecha_30d
			)
		);
		$tot_a       = (int) ( $asistencias->total ?? 0 );
		$asi_a       = (int) ( $asistencias->asistidas ?? 0 );
		$pct_asist   = $tot_a > 0 ? round( ( $asi_a / $tot_a ) * 100, 1 ) : 0.0;

		$tabla_msg = $wpdb->prefix . 'ed_msg_recepciones';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$mensajes_pendientes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tabla_msg} WHERE leido = 0" );

		$torneos_activos = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT pm.post_id) FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} t ON t.ID = pm.post_id AND t.post_type = 'torneo' AND t.post_status = 'publish'
			WHERE pm.meta_key = 'ed_tor_estado' AND pm.meta_value IN ('inscripciones','en_curso')"
		);

		$kpis = array(
			'inscripciones_activas' => $jugadores_activos,
			'ingresos_mes'          => $ingresos_mes,
			'ingresos_temporada'    => $ingresos_temporada,
			'pct_pagados'           => $pct_pagados,
			'total_plazos'          => $total_plazos,
			'plazos_pendientes'     => (int) ( $pagos->pendientes ?? 0 ),
			'plazos_fallidos'       => (int) ( $pagos->fallidos ?? 0 ),
			'pct_asistencia'        => $pct_asist,
			'mensajes_pendientes'   => $mensajes_pendientes,
			'torneos_activos'       => $torneos_activos,
		);

		set_transient( $cache_key, $kpis, self::CACHE_TTL );
		return $kpis;
	}

	/**
	 * @return array<int, array{mes:string,label:string,total:float}>
	 */
	public static function get_ingresos_por_mes(): array {
		$cache_key = 'ed_dashboard_ingresos_mes_v1';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_pagos_plazos';
		$since = gmdate( 'Y-m-d H:i:s', strtotime( '-12 months', (int) current_time( 'timestamp' ) ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DATE_FORMAT(fecha_pagado, '%%Y-%%m') AS mes, SUM(importe) AS total
				FROM {$tabla}
				WHERE estado = 'pagado' AND fecha_pagado IS NOT NULL AND fecha_pagado >= %s
				GROUP BY mes ORDER BY mes ASC",
				$since
			)
		);

		$resultado = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $r ) {
				$mes = (string) $r->mes;
				$resultado[] = array(
					'mes'   => $mes,
					'label' => date_i18n( 'M Y', strtotime( $mes . '-01 12:00:00' ) ),
					'total' => (float) $r->total,
				);
			}
		}

		set_transient( $cache_key, $resultado, self::CACHE_TTL );
		return $resultado;
	}

	/**
	 * @return array<int, array{deporte_id:int,nombre:string,total:int}>
	 */
	public static function get_inscripciones_por_deporte(): array {
		$cache_key = 'ed_dashboard_inscripciones_deporte_v1';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			"SELECT pm.meta_value AS deporte_id, d.post_title AS deporte_nombre, COUNT(*) AS total
			FROM {$wpdb->postmeta} pm
			INNER JOIN {$wpdb->posts} j ON j.ID = pm.post_id AND j.post_type = 'jugador' AND j.post_status = 'publish'
			INNER JOIN {$wpdb->posts} d ON d.ID = pm.meta_value AND d.post_type = 'deporte'
			WHERE pm.meta_key = 'ed_jugador_deporte' AND pm.meta_value != '' AND pm.meta_value != '0'
			GROUP BY pm.meta_value
			ORDER BY total DESC"
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'deporte_id' => (int) $r->deporte_id,
				'nombre'     => (string) $r->deporte_nombre,
				'total'      => (int) $r->total,
			);
		}

		set_transient( $cache_key, $out, self::CACHE_TTL );
		return $out;
	}

	/**
	 * @return array<int, array{categoria_id:int,categoria:string,total_registros:int,asistencias:int,pct:float}>
	 */
	public static function get_asistencia_por_categoria(): array {
		$cache_key = 'ed_dashboard_asistencia_cat_v1';
		$cached    = get_transient( $cache_key );
		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$tabla_a = $wpdb->prefix . 'ed_asistencias';
		$fecha   = gmdate( 'Y-m-d', strtotime( '-30 days', (int) current_time( 'timestamp' ) ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pm_cat.meta_value AS categoria_id, c.post_title AS categoria,
					COUNT(*) AS total_registros,
					SUM(a.estado = 'asistio') AS asistencias,
					ROUND(SUM(a.estado = 'asistio') / COUNT(*) * 100, 1) AS pct
				FROM {$tabla_a} a
				INNER JOIN {$wpdb->postmeta} pm_cat ON pm_cat.post_id = a.jugador_id AND pm_cat.meta_key = 'ed_jugador_categoria'
				INNER JOIN {$wpdb->posts} c ON c.ID = pm_cat.meta_value AND c.post_type = 'categoria'
				WHERE a.tipo_sesion = 'entrenamiento' AND a.fecha >= %s
				GROUP BY pm_cat.meta_value
				ORDER BY pct ASC",
				$fecha
			)
		);

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$out = array();
		foreach ( $rows as $r ) {
			$out[] = array(
				'categoria_id'    => (int) $r->categoria_id,
				'categoria'       => (string) $r->categoria,
				'total_registros' => (int) $r->total_registros,
				'asistencias'     => (int) $r->asistencias,
				'pct'             => (float) $r->pct,
			);
		}

		set_transient( $cache_key, $out, self::CACHE_TTL );
		return $out;
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_impagos(): array {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_pagos_plazos';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			"SELECT pp.nucleo_id, pp.jugador_id, p_j.post_title AS jugador_nombre,
				pp.deporte_id, p_d.post_title AS deporte_nombre, pp.importe, pp.fecha_prevista, pp.estado, pp.plazo
			FROM {$tabla} pp
			INNER JOIN {$wpdb->posts} p_j ON p_j.ID = pp.jugador_id
			INNER JOIN {$wpdb->posts} p_d ON p_d.ID = pp.deporte_id
			WHERE pp.estado IN ('cancelado','fallido') AND pp.plazo = 2
			ORDER BY pp.fecha_prevista ASC
			LIMIT 50"
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_baja_asistencia( float $umbral = 60.0 ): array {
		global $wpdb;
		$tabla_a = $wpdb->prefix . 'ed_asistencias';
		$fecha   = gmdate( 'Y-m-d', strtotime( '-30 days', (int) current_time( 'timestamp' ) ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT a.jugador_id, p.post_title AS jugador_nombre, COUNT(*) AS total,
					SUM(a.estado = 'asistio') AS asistencias,
					ROUND(SUM(a.estado = 'asistio') / COUNT(*) * 100, 1) AS pct
				FROM {$tabla_a} a
				INNER JOIN {$wpdb->posts} p ON p.ID = a.jugador_id
				WHERE a.tipo_sesion = 'entrenamiento' AND a.fecha >= %s
				GROUP BY a.jugador_id
				HAVING pct < %f AND total >= 3
				ORDER BY pct ASC
				LIMIT 30",
				$fecha,
				$umbral
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	public static function invalidar_cache(): void {
		delete_transient( 'ed_dashboard_kpis_v1' );
		delete_transient( 'ed_dashboard_ingresos_mes_v1' );
		delete_transient( 'ed_dashboard_inscripciones_deporte_v1' );
		delete_transient( 'ed_dashboard_asistencia_cat_v1' );
	}

	/**
	 * @param string $tipo jugadores|pagos|asistencias|impagos|baja_asistencia.
	 */
	public static function exportar_csv( string $tipo ): void {
		if ( ! self::current_user_can_export() ) {
			status_header( 403 );
			exit;
		}

		$tipo = sanitize_key( $tipo );
		if ( 'baja_asistencia' === $tipo ) {
			self::export_baja_asistencia_csv();
			exit;
		}

		global $wpdb;

		$archivos = array(
			'jugadores'   => array(
				'nombre' => 'jugadores.csv',
				'query'  => "SELECT p.ID, p.post_title AS nombre_post,
					MAX(CASE WHEN pm.meta_key = 'ed_jugador_nombre' THEN pm.meta_value END) AS nombre,
					MAX(CASE WHEN pm.meta_key = 'ed_jugador_apellidos' THEN pm.meta_value END) AS apellidos,
					MAX(CASE WHEN pm.meta_key = 'ed_jugador_fecha_nacimiento' THEN pm.meta_value END) AS fecha_nacimiento,
					MAX(CASE WHEN pm.meta_key = 'ed_jugador_estado' THEN pm.meta_value END) AS estado
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key IN (
					'ed_jugador_nombre','ed_jugador_apellidos','ed_jugador_fecha_nacimiento','ed_jugador_estado'
				)
				WHERE p.post_type = 'jugador' AND p.post_status = 'publish'
				GROUP BY p.ID, p.post_title
				ORDER BY p.post_title",
				'cols'   => array( 'ID', 'nombre_post', 'nombre', 'apellidos', 'fecha_nacimiento', 'estado' ),
			),
			'pagos'       => array(
				'nombre' => 'pagos.csv',
				'query'  => "SELECT pp.id, pj.post_title AS jugador, pd.post_title AS deporte, pp.plazo, pp.importe,
					pp.estado, pp.fecha_prevista, pp.fecha_pagado, pp.pasarela
				FROM {$wpdb->prefix}ed_pagos_plazos pp
				LEFT JOIN {$wpdb->posts} pj ON pj.ID = pp.jugador_id
				LEFT JOIN {$wpdb->posts} pd ON pd.ID = pp.deporte_id
				ORDER BY pp.fecha_prevista ASC",
				'cols'   => array( 'id', 'jugador', 'deporte', 'plazo', 'importe', 'estado', 'fecha_prevista', 'fecha_pagado', 'pasarela' ),
			),
			'asistencias' => array(
				'nombre' => 'asistencias.csv',
				'query'  => "SELECT a.id, p.post_title AS jugador, a.tipo_sesion, a.estado, a.fecha, a.motivo
				FROM {$wpdb->prefix}ed_asistencias a
				LEFT JOIN {$wpdb->posts} p ON p.ID = a.jugador_id
				ORDER BY a.fecha DESC
				LIMIT 5000",
				'cols'   => array( 'id', 'jugador', 'tipo_sesion', 'estado', 'fecha', 'motivo' ),
			),
			'impagos'     => array(
				'nombre' => 'impagos.csv',
				'query'  => "SELECT pp.id, pj.post_title AS jugador, pd.post_title AS deporte, pp.importe, pp.estado, pp.fecha_prevista
				FROM {$wpdb->prefix}ed_pagos_plazos pp
				LEFT JOIN {$wpdb->posts} pj ON pj.ID = pp.jugador_id
				LEFT JOIN {$wpdb->posts} pd ON pd.ID = pp.deporte_id
				WHERE pp.estado IN ('cancelado','fallido') AND pp.plazo = 2
				ORDER BY pp.fecha_prevista ASC",
				'cols'   => array( 'id', 'jugador', 'deporte', 'importe', 'estado', 'fecha_prevista' ),
			),
		);

		if ( ! isset( $archivos[ $tipo ] ) ) {
			status_header( 404 );
			exit;
		}

		$cfg  = $archivos[ $tipo ];
		$rows = $wpdb->get_results( $cfg['query'], ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		self::send_csv_headers( $cfg['nombre'] );
		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		fputcsv( $output, $cfg['cols'], ';' );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$linea = array();
				foreach ( $cfg['cols'] as $col ) {
					$linea[] = self::sanitize_csv_cell( isset( $row[ $col ] ) ? $row[ $col ] : '' );
				}
				fputcsv( $output, $linea, ';' );
			}
		}
		fclose( $output );
		exit;
	}

	private static function export_baja_asistencia_csv(): void {
		$rows = self::get_baja_asistencia();
		self::send_csv_headers( 'baja_asistencia.csv' );
		$output = fopen( 'php://output', 'w' );
		if ( false === $output ) {
			exit;
		}
		fprintf( $output, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		$cols = array( 'jugador_id', 'jugador_nombre', 'total', 'asistencias', 'pct' );
		fputcsv( $output, $cols, ';' );
		foreach ( $rows as $r ) {
			fputcsv(
				$output,
				array(
					self::sanitize_csv_cell( $r->jugador_id ),
					self::sanitize_csv_cell( $r->jugador_nombre ),
					self::sanitize_csv_cell( $r->total ),
					self::sanitize_csv_cell( $r->asistencias ),
					self::sanitize_csv_cell( $r->pct ),
				),
				';'
			);
		}
		fclose( $output );
	}

	/**
	 * Neutralitza cel·les que podrien ser interpretades com fórmules per Excel/LibreOffice
	 * (CSV injection). Qualsevol valor que comenci per =, +, -, @ es prefixia amb una tabulació.
	 *
	 * @param mixed $value
	 * @return string
	 */
	private static function sanitize_csv_cell( mixed $value ): string {
		$str = (string) $value;
		if ( '' !== $str && in_array( $str[0], array( '=', '+', '-', '@', "\t", "\r", "\n" ), true ) ) {
			return "\t" . $str;
		}
		return $str;
	}

	private static function send_csv_headers( string $filename ): void {
		nocache_headers();
		header( 'Content-Type: text/csv; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
		header( 'Pragma: no-cache' );
	}

	public static function current_user_can_export(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_escuela_deportiva' );
	}

	public static function current_user_can_view_dashboard(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_escuela_deportiva' );
	}
}
