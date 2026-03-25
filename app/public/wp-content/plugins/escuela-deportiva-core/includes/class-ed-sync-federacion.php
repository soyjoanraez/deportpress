<?php
/**
 * Coordinació de la sincronització amb FFCV (Fase 7).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Orquestra el scraper i persisteix a les taules ed_fed_*.
 */
class ED_Sync_Federacion {

	/**
	 * @param bool $forzar Si és cert, ignora el toggle «activa» (sync manual / REST).
	 */
	public static function sincronizar( bool $forzar = false ): void {
		$config = self::get_config();
		if ( ! $forzar && empty( $config['activa'] ) ) {
			return;
		}

		$categorias = isset( $config['categorias'] ) && is_array( $config['categorias'] ) ? $config['categorias'] : array();
		if ( empty( $categorias ) ) {
			return;
		}

		$scraper = new ED_Scraper_FFCV();

		foreach ( $categorias as $cat_config ) {
			if ( empty( $cat_config['competicion_id'] ) ) {
				continue;
			}
			$nombre          = (string) ( $cat_config['nombre'] ?? '' );
			$competicion_id  = (string) $cat_config['competicion_id'];
			$grupo_id        = isset( $cat_config['grupo_id'] ) && '' !== (string) $cat_config['grupo_id'] ? (string) $cat_config['grupo_id'] : null;
			$competicion_nom = (string) ( $cat_config['competicion_nombre'] ?? $competicion_id );

			try {
				$partidos = $scraper->get_partidos( $competicion_id, $grupo_id );
				foreach ( $partidos as $partido ) {
					$partido['categoria']    = $nombre;
					$partido['competicion']  = $competicion_nom;
					$partido['_config_cat']  = $cat_config;
					self::upsert_partido( $partido, $cat_config );
				}

				$clasificacion = $scraper->get_clasificacion( $competicion_id, $grupo_id );
				foreach ( $clasificacion as $fila ) {
					$fila['categoria']   = $nombre;
					$fila['competicion'] = $competicion_nom;
					self::upsert_clasificacion( $fila, $cat_config );
				}

				sleep( 2 );
			} catch ( Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[DeportPress FFCV] ' . $nombre . ': ' . $e->getMessage() );
			}
		}

		update_option( 'ed_ffcv_ultima_sync', current_time( 'mysql' ) );
		do_action( 'ed_ffcv_sync_completada' );
	}

	/**
	 * @param array<string, mixed>  $p
	 * @param array<string, mixed> $cat_config
	 */
	private static function upsert_partido( array $p, array $cat_config ): void {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_fed_partidos';

		$nombre_club = (string) get_option( 'ed_nombre_club', 'Ondara' );
		$es_nuestro  = ( false !== stripos( (string) $p['equipo_local'], $nombre_club ) || false !== stripos( (string) $p['equipo_visitante'], $nombre_club ) ) ? 1 : 0;

		$cat_ffcv = (string) ( $p['categoria'] ?? '' );
		$comp_key = (string) ( $p['competicion'] ?? '' );
		$cat_loc  = isset( $cat_config['categoria_local'] ) ? (int) $cat_config['categoria_local'] : 0;
		$cat_id   = $cat_loc > 0 ? $cat_loc : self::get_categoria_local( $cat_ffcv, $comp_key );

		$temporada = (string) get_option( 'ed_temporada_actual', '2025-26' );

		$data = array(
			'ffcv_id'           => (string) $p['id'],
			'competicion'       => $comp_key,
			'jornada'           => isset( $p['jornada'] ) ? (int) $p['jornada'] : null,
			'categoria_ffcv'    => $cat_ffcv,
			'categoria_id'      => $cat_id > 0 ? $cat_id : null,
			'equipo_local'      => (string) $p['equipo_local'],
			'equipo_visitante'  => (string) $p['equipo_visitante'],
			'es_nuestro_equipo' => $es_nuestro,
			'fecha_hora'        => isset( $p['fecha_hora'] ) ? (string) $p['fecha_hora'] : null,
			'campo'             => isset( $p['campo'] ) ? (string) $p['campo'] : null,
			'goles_local'       => isset( $p['goles_local'] ) ? (int) $p['goles_local'] : null,
			'goles_visitante'   => isset( $p['goles_visitante'] ) ? (int) $p['goles_visitante'] : null,
			'estado'            => isset( $p['estado'] ) ? (string) $p['estado'] : 'programado',
			'temporada'         => $temporada,
			'ultima_sync'       => current_time( 'mysql' ),
		);

		$existente = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$tabla} WHERE ffcv_id = %s",
				$data['ffcv_id']
			)
		);

		if ( $existente ) {
			unset( $data['ffcv_id'] );
			$wpdb->update(
				$tabla,
				$data,
				array( 'id' => (int) $existente ),
				array( '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert(
				$tabla,
				$data,
				array( '%s', '%s', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * @param array<string, mixed>  $fila
	 * @param array<string, mixed> $cat_config
	 */
	private static function upsert_clasificacion( array $fila, array $cat_config ): void {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_fed_clasificacion';

		$nombre_club = (string) get_option( 'ed_nombre_club', 'Ondara' );
		$equipo      = (string) ( $fila['equipo'] ?? '' );
		if ( '' === $equipo ) {
			return;
		}

		$es_nuestro = false !== stripos( $equipo, $nombre_club ) ? 1 : 0;

		$competicion  = (string) ( $fila['competicion'] ?? '' );
		$categoria_ff = (string) ( $fila['categoria'] ?? ( $cat_config['nombre'] ?? '' ) );
		$temporada    = (string) get_option( 'ed_temporada_actual', '2025-26' );

		$uq_hash = md5( $temporada . '|' . $competicion . '|' . $categoria_ff . '|' . $equipo );

		$row = array(
			'uq_hash'        => $uq_hash,
			'competicion'    => $competicion,
			'categoria_ffcv' => $categoria_ff,
			'equipo'         => $equipo,
			'es_nuestro'     => $es_nuestro,
			'pj'             => (int) ( $fila['pj'] ?? 0 ),
			'pg'             => (int) ( $fila['pg'] ?? 0 ),
			'pe'             => (int) ( $fila['pe'] ?? 0 ),
			'pp'             => (int) ( $fila['pp'] ?? 0 ),
			'gf'             => (int) ( $fila['gf'] ?? 0 ),
			'gc'             => (int) ( $fila['gc'] ?? 0 ),
			'puntos'         => (int) ( $fila['puntos'] ?? 0 ),
			'posicion'       => (int) ( $fila['posicion'] ?? 0 ),
			'temporada'      => $temporada,
		);

		$wpdb->replace(
			$tabla,
			$row,
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s' )
		);
	}

	private static function get_categoria_local( string $cat_ffcv, string $competicion ): ?int {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_fed_mapeo';
		if ( '' === $cat_ffcv ) {
			return null;
		}

		if ( '' !== $competicion ) {
			$m = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT categoria_id FROM {$tabla} WHERE categoria_ffcv = %s AND competicion = %s LIMIT 1",
					$cat_ffcv,
					$competicion
				)
			);
			if ( $m ) {
				return (int) $m;
			}
		}

		$m = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT categoria_id FROM {$tabla} WHERE categoria_ffcv = %s AND competicion = '' LIMIT 1",
				$cat_ffcv
			)
		);

		return $m ? (int) $m : null;
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_proximos_partidos( int $limit = 10 ): array {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_fed_partidos';
		$lim   = max( 1, min( 50, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tabla}
				WHERE es_nuestro_equipo = 1
				AND estado = 'programado'
				AND fecha_hora IS NOT NULL AND fecha_hora >= %s
				ORDER BY fecha_hora ASC
				LIMIT %d",
				current_time( 'mysql' ),
				$lim
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_resultados_recientes( int $limit = 10 ): array {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_fed_partidos';
		$lim   = max( 1, min( 50, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tabla}
				WHERE es_nuestro_equipo = 1
				AND estado = 'jugado'
				ORDER BY fecha_hora DESC
				LIMIT %d",
				$lim
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_partidos_categoria( int $categoria_id, int $limit = 20 ): array {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_fed_partidos';
		$lim   = max( 1, min( 100, $limit ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$tabla} WHERE categoria_id = %d ORDER BY fecha_hora DESC LIMIT %d",
				$categoria_id,
				$lim
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_clasificacion_categoria( int $categoria_id ): array {
		global $wpdb;

		$cat_ffcv = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT categoria_ffcv FROM {$wpdb->prefix}ed_fed_mapeo WHERE categoria_id = %d LIMIT 1",
				$categoria_id
			)
		);

		if ( ! $cat_ffcv ) {
			return array();
		}

		$temporada = (string) get_option( 'ed_temporada_actual', '2025-26' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ed_fed_clasificacion
				WHERE categoria_ffcv = %s AND temporada = %s
				ORDER BY posicion ASC",
				$cat_ffcv,
				$temporada
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array{activa:bool,categorias:array<int, array<string, mixed>>}
	 */
	public static function get_config(): array {
		$def = array(
			'activa'     => false,
			'categorias' => array(),
		);
		$opt = get_option( 'ed_ffcv_config', $def );
		if ( ! is_array( $opt ) ) {
			return $def;
		}
		$opt['activa']     = ! empty( $opt['activa'] );
		$opt['categorias'] = isset( $opt['categorias'] ) && is_array( $opt['categorias'] ) ? $opt['categorias'] : array();
		return $opt;
	}

	/**
	 * @param array{activa?:bool,categorias?:array<int, array<string, mixed>>} $config
	 */
	public static function save_config( array $config ): void {
		$current = self::get_config();
		if ( isset( $config['activa'] ) ) {
			$current['activa'] = (bool) $config['activa'];
		}
		if ( isset( $config['categorias'] ) && is_array( $config['categorias'] ) ) {
			$current['categorias'] = $config['categorias'];
		}
		update_option( 'ed_ffcv_config', $current );
	}
}
