<?php
/**
 * Torneos i partits (Fase 3/4): URLs, cuadre, inscripcions.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Camps ACF partit / torneo (prefix ed_par_ / ed_tor_).
 */
class ED_Torneos {

	public const PAR_TORNEO        = 'ed_par_torneo';
	public const PAR_EQ_LOCAL      = 'ed_par_equipo_local';
	public const PAR_EQ_VISITANTE  = 'ed_par_equipo_visitante';
	public const PAR_FECHA_HORA    = 'ed_par_fecha_hora';
	public const PAR_LUGAR         = 'ed_par_lugar';
	public const PAR_FASE          = 'ed_par_fase';
	public const PAR_GRUPO         = 'ed_par_grupo';
	public const PAR_ESTADO        = 'ed_par_estado';
	public const PAR_GOLES_LOCAL   = 'ed_par_goles_local';
	public const PAR_GOLES_VIS     = 'ed_par_goles_visitante';
	public const PAR_MINUTO        = 'ed_par_minuto_actual';
	public const PAR_OPERADOR      = 'ed_par_operador';
	public const PAR_MVP           = 'ed_par_mvp_jugador';
	public const PAR_CRONICA       = 'ed_par_cronica';

	public const TOR_DEPORTE            = 'ed_tor_deporte';
	public const TOR_EQUIPOS_INSCRITOS  = 'ed_tor_equipos_inscritos';

	/** Fase 6 — entrades, rifa, bar */
	public const TOR_ENTRADAS_ACTIVAS    = 'ed_tor_entradas_activas';
	public const TOR_PRECIO_ENTRADA      = 'ed_tor_precio_entrada';
	public const TOR_AFORO_MAXIMO        = 'ed_tor_aforo_maximo';
	public const TOR_WC_PRODUCT_ENTRADA  = 'ed_tor_wc_product_entrada';
	public const TOR_RIFA_ACTIVA         = 'ed_tor_rifa_activa';
	public const TOR_RIFA_NUMS_ENTRADA   = 'ed_tor_rifa_numeros_por_entrada';
	public const TOR_RIFA_RANGO_INICIO   = 'ed_tor_rifa_rango_inicio';
	public const TOR_RIFA_RANGO_FIN      = 'ed_tor_rifa_rango_fin';
	public const TOR_BAR_ACTIVO          = 'ed_tor_bar_activo';
	public const TOR_BAR_FRANJAS         = 'ed_tor_bar_franjas_horarias';
	public const TOR_BAR_HORA_CIERRE     = 'ed_tor_bar_hora_cierre';

	/**
	 * URL pública del partit (pretty o permalink).
	 */
	public static function get_url_partido( int $partido_id ): string {
		if ( ! function_exists( 'get_field' ) ) {
			return get_permalink( $partido_id ) ?: '';
		}
		$torneo_id    = (int) get_field( self::PAR_TORNEO, $partido_id, false );
		$local_id     = (int) get_field( self::PAR_EQ_LOCAL, $partido_id, false );
		$visitante_id = (int) get_field( self::PAR_EQ_VISITANTE, $partido_id, false );
		if ( ! $torneo_id || ! $local_id || ! $visitante_id ) {
			$link = get_permalink( $partido_id );
			return $link ? $link : '';
		}
		$torneo_slug    = get_post_field( 'post_name', $torneo_id );
		$local_slug     = get_post_field( 'post_name', $local_id );
		$visitante_slug = get_post_field( 'post_name', $visitante_id );
		if ( ! $torneo_slug || ! $local_slug || ! $visitante_slug ) {
			$link = get_permalink( $partido_id );
			return $link ? $link : '';
		}
		return home_url( "/torneo/{$torneo_slug}/partido/{$local_slug}-vs-{$visitante_slug}/" );
	}

	/**
	 * Inscriure equip al torneig (camp relationship).
	 */
	public static function inscribir_equipo( int $torneo_id, int $equipo_id ): bool {
		if ( ! function_exists( 'get_field' ) ) {
			return false;
		}
		$inscritos = get_field( self::TOR_EQUIPOS_INSCRITOS, $torneo_id, false );
		$inscritos = is_array( $inscritos ) ? array_map( 'intval', $inscritos ) : array();
		if ( in_array( $equipo_id, $inscritos, true ) ) {
			return false;
		}
		$max = (int) get_field( 'ed_tor_max_equipos', $torneo_id );
		if ( $max > 0 && count( $inscritos ) >= $max ) {
			return false;
		}
		$inscritos[] = $equipo_id;
		update_field( self::TOR_EQUIPOS_INSCRITOS, $inscritos, $torneo_id );
		do_action( 'ed_equipo_inscrito', $torneo_id, $equipo_id );
		return true;
	}

	/**
	 * @return array<int, array{id:int,nombre:string,escudo:?string,localidad:?string}>
	 */
	public static function get_equipos( int $torneo_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}
		$ids = get_field( self::TOR_EQUIPOS_INSCRITOS, $torneo_id, false );
		if ( empty( $ids ) || ! is_array( $ids ) ) {
			return array();
		}
		$out = array();
		foreach ( array_map( 'intval', $ids ) as $id ) {
			if ( $id <= 0 ) {
				continue;
			}
			$esc_id = get_field( 'ed_eq_escudo', $id );
			$out[]  = array(
				'id'        => $id,
				'nombre'    => get_post_field( 'post_title', $id ),
				'escudo'    => $esc_id ? wp_get_attachment_image_url( (int) $esc_id, 'thumbnail' ) : null,
				'localidad' => function_exists( 'get_field' ) ? (string) get_field( 'ed_eq_localidad', $id ) : null,
			);
		}
		return $out;
	}

	/**
	 * Partits del torneig agrupats per fase.
	 *
	 * @return array<string, array<int, array<string, mixed>>>
	 */
	public static function get_cuadro( int $torneo_id ): array {
		$partidos = get_posts(
			array(
				'post_type'      => 'partido',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_key'       => self::PAR_TORNEO,
				'meta_value'     => $torneo_id,
				'orderby'        => 'date',
				'order'          => 'ASC',
			)
		);
		$cuadro = array(
			'cuartos' => array(),
			'semis'   => array(),
			'final'   => array(),
			'grupo'   => array(),
			'amistoso'=> array(),
		);
		foreach ( $partidos as $p ) {
			$fase = function_exists( 'get_field' ) ? (string) get_field( self::PAR_FASE, $p->ID ) : 'otro';
			if ( ! isset( $cuadro[ $fase ] ) ) {
				$cuadro[ $fase ] = array();
			}
			$cuadro[ $fase ][] = self::format_partido( $p->ID );
		}
		return $cuadro;
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function format_partido( int $partido_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array( 'id' => $partido_id );
		}
		$local_id     = (int) get_field( self::PAR_EQ_LOCAL, $partido_id, false );
		$visitante_id = (int) get_field( self::PAR_EQ_VISITANTE, $partido_id, false );
		$torneo_id    = (int) get_field( self::PAR_TORNEO, $partido_id, false );
		return array(
			'id'               => $partido_id,
			'torneo_id'        => $torneo_id,
			'slug'             => get_post_field( 'post_name', $partido_id ),
			'url'              => self::get_url_partido( $partido_id ),
			'estado'           => (string) get_field( self::PAR_ESTADO, $partido_id ),
			'fase'             => (string) get_field( self::PAR_FASE, $partido_id ),
			'fecha_hora'       => (string) get_field( self::PAR_FECHA_HORA, $partido_id ),
			'lugar'            => (string) get_field( self::PAR_LUGAR, $partido_id ),
			'minuto_actual'    => (int) get_field( self::PAR_MINUTO, $partido_id ),
			'equipo_local'     => self::format_equipo( $local_id ),
			'equipo_visitante' => self::format_equipo( $visitante_id ),
			'goles_local'      => (int) get_field( self::PAR_GOLES_LOCAL, $partido_id ),
			'goles_visitante'  => (int) get_field( self::PAR_GOLES_VIS, $partido_id ),
			'mvp'              => (int) get_field( self::PAR_MVP, $partido_id, false ),
		);
	}

	/**
	 * @return ?array{id:int,nombre:string,escudo:?string,color:?string}
	 */
	private static function format_equipo( int $id ): ?array {
		if ( $id <= 0 ) {
			return null;
		}
		$esc = function_exists( 'get_field' ) ? get_field( 'ed_eq_escudo', $id ) : null;
		return array(
			'id'     => $id,
			'nombre' => get_post_field( 'post_title', $id ),
			'escudo' => $esc ? wp_get_attachment_image_url( (int) $esc, 'medium' ) : null,
			'color'  => function_exists( 'get_field' ) ? (string) get_field( 'ed_eq_color_principal', $id ) : '',
		);
	}

	/**
	 * Genera partits d’eliminatòria (esborrany mínim del spec).
	 *
	 * @param int[] $equipos_ids
	 * @return array{fase?:string,partidos?:int[],error?:string}
	 */
	public static function generar_cuadro_eliminatorio( int $torneo_id, array $equipos_ids ): array {
		$equipos_ids = array_values( array_filter( array_map( 'intval', $equipos_ids ) ) );
		$total       = count( $equipos_ids );
		if ( $total < 2 ) {
			return array( 'error' => __( 'Calen almenys 2 equips.', 'escuela-deportiva-core' ) );
		}
		if ( $total >= 5 ) {
			$fase = 'cuartos';
		} elseif ( $total >= 3 ) {
			$fase = 'semis';
		} else {
			$fase = 'final';
		}
		$creados = array();
		$pares   = array_chunk( $equipos_ids, 2 );
		foreach ( $pares as $par ) {
			$local     = $par[0];
			$visitante = isset( $par[1] ) ? $par[1] : 0;
			$title     = sprintf(
				'%s vs %s — %s',
				get_post_field( 'post_title', $local ),
				$visitante ? get_post_field( 'post_title', $visitante ) : 'BYE',
				get_post_field( 'post_title', $torneo_id )
			);
			$partido_id = wp_insert_post(
				array(
					'post_type'   => 'partido',
					'post_status' => 'publish',
					'post_title'  => $title,
				),
				true
			);
			if ( is_wp_error( $partido_id ) ) {
				continue;
			}
			if ( function_exists( 'update_field' ) ) {
				update_field( self::PAR_TORNEO, $torneo_id, $partido_id );
				update_field( self::PAR_EQ_LOCAL, $local, $partido_id );
				update_field( self::PAR_EQ_VISITANTE, $visitante, $partido_id );
				update_field( self::PAR_FASE, $fase, $partido_id );
				update_field( self::PAR_ESTADO, 'programado', $partido_id );
				update_field( self::PAR_GOLES_LOCAL, 0, $partido_id );
				update_field( self::PAR_GOLES_VIS, 0, $partido_id );
				update_field( self::PAR_MINUTO, 0, $partido_id );
			}
			$creados[] = (int) $partido_id;
			if ( ! $visitante ) {
				do_action( 'ed_bye_avanzar', $torneo_id, $local, $fase );
			}
		}
		return array( 'fase' => $fase, 'partidos' => $creados );
	}

	/**
	 * Hooks para el avance automático en el cuadro eliminatorio.
	 */
	public static function register_hooks(): void {
		add_action( 'ed_evento_partido_registrado', array( self::class, 'handle_fin_partido' ), 10, 3 );
		add_action( 'ed_bye_avanzar', array( self::class, 'handle_bye_avanzar' ), 10, 3 );
	}

	/**
	 * Callback para procesar el ganador cuando finaliza un partido.
	 */
	public static function handle_fin_partido( int $evento_id, int $partido_id, string $tipo ): void {
		if ( 'fin_partido' !== $tipo ) {
			return;
		}
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$goles_local = (int) get_field( self::PAR_GOLES_LOCAL, $partido_id );
		$goles_vis   = (int) get_field( self::PAR_GOLES_VIS, $partido_id );

		$local_id  = (int) get_field( self::PAR_EQ_LOCAL, $partido_id );
		$vis_id    = (int) get_field( self::PAR_EQ_VISITANTE, $partido_id );
		$torneo_id = (int) get_field( self::PAR_TORNEO, $partido_id );
		$fase      = (string) get_field( self::PAR_FASE, $partido_id );

		if ( $goles_local > $goles_vis ) {
			self::avanzar_ganador( $torneo_id, $local_id, $fase );
		} elseif ( $goles_vis > $goles_local ) {
			self::avanzar_ganador( $torneo_id, $vis_id, $fase );
		}
	}

	/**
	 * Callback para avanzar automáticamente cuando un equipo tiene BYE.
	 */
	public static function handle_bye_avanzar( int $torneo_id, int $equipo_id, string $fase_actual ): void {
		self::avanzar_ganador( $torneo_id, $equipo_id, $fase_actual );
	}

	/**
	 * Avanza a un equipo a la siguiente ronda buscando un partido disponible o creando uno nuevo.
	 */
	public static function avanzar_ganador( int $torneo_id, int $equipo_id, string $fase_actual ): void {
		$mapa_fases = array(
			'cuartos' => 'semis',
			'semis'   => 'final',
		);
		$siguiente = $mapa_fases[ $fase_actual ] ?? null;
		if ( ! $siguiente ) {
			return;
		}

		$partidos_siguiente = get_posts(
			array(
				'post_type'      => 'partido',
				'posts_per_page' => -1,
				'meta_query'     => array(
					'relation' => 'AND',
					array( 'key' => self::PAR_TORNEO, 'value' => $torneo_id ),
					array( 'key' => self::PAR_FASE,   'value' => $siguiente ),
				),
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);

		$hueco_encontrado = false;
		foreach ( $partidos_siguiente as $p ) {
			$loc = (int) get_field( self::PAR_EQ_LOCAL, $p->ID );
			$vis = (int) get_field( self::PAR_EQ_VISITANTE, $p->ID );

			if ( ! $loc ) {
				update_field( self::PAR_EQ_LOCAL, $equipo_id, $p->ID );
				$hueco_encontrado = true;
				break;
			} elseif ( ! $vis ) {
				update_field( self::PAR_EQ_VISITANTE, $equipo_id, $p->ID );
				$loc_nombre    = get_post_field( 'post_title', $loc );
				$vis_nombre    = get_post_field( 'post_title', $equipo_id );
				$torneo_nombre = get_post_field( 'post_title', $torneo_id );
				wp_update_post(
					array(
						'ID'         => $p->ID,
						'post_title' => sprintf( '%s vs %s — %s', $loc_nombre, $vis_nombre, $torneo_nombre ),
					)
				);
				$hueco_encontrado = true;
				break;
			}
		}

		if ( ! $hueco_encontrado ) {
			$vis_nombre    = get_post_field( 'post_title', $equipo_id );
			$torneo_nombre = get_post_field( 'post_title', $torneo_id );
			$title         = sprintf( '%s vs TBD — %s', $vis_nombre, $torneo_nombre );

			$pid = wp_insert_post(
				array(
					'post_type'   => 'partido',
					'post_status' => 'publish',
					'post_title'  => $title,
				)
			);
			if ( ! is_wp_error( $pid ) ) {
				update_field( self::PAR_TORNEO, $torneo_id, $pid );
				update_field( self::PAR_FASE, $siguiente, $pid );
				update_field( self::PAR_EQ_LOCAL, $equipo_id, $pid );
				update_field( self::PAR_EQ_VISITANTE, 0, $pid );
				update_field( self::PAR_ESTADO, 'programado', $pid );
				update_field( self::PAR_GOLES_LOCAL, 0, $pid );
				update_field( self::PAR_GOLES_VIS, 0, $pid );
				update_field( self::PAR_MINUTO, 0, $pid );
			}
		}
	}
}
