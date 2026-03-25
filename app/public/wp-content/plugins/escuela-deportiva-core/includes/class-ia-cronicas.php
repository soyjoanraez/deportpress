<?php
/**
 * Cròniques de partit amb OpenAI (Fase 5).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cua i generació via API.
 */
class ED_IA_Cronicas {

	private const GLOSARIO = array(
		'hat_trick'    => 'tres gols del mateix jugador en un partit',
		'remontada'    => 'remuntar un resultat advers per a guanyar',
		'goleada'      => 'victòria amb diferència de 3 o més gols',
		'paliza'       => 'derrota abultada (4+ gols de diferència)',
		'empate'       => 'resultat empat al final del partit',
		'gol_temprano' => 'gol marcat en els primers 10 minuts',
		'gol_agónico'  => 'gol marcat en els últims 5 minuts o al descompte',
		'penalti'      => 'llançament des del punt de penal',
		'tarjeta_roja' => 'expulsió d’un jugador del camp',
		'sin_goles'    => 'partit 0-0 sense gols',
	);

	public static function register(): void {
		add_filter( 'cron_schedules', array( self::class, 'add_cron_schedule' ) );
		add_action( 'ed_mvp_cerrado', array( self::class, 'on_mvp_cerrado' ), 10, 2 );
		add_action( 'ed_evento_partido_registrado', array( self::class, 'on_evento_partido' ), 25, 6 );
		add_action( 'ed_encolar_cronica_fallback', array( self::class, 'encolar' ), 10, 1 );
		add_action( 'admin_post_ed_generar_cronica', array( self::class, 'handle_admin_generar' ) );
	}

	public static function registrar_cron(): void {
		if ( ! wp_next_scheduled( 'ed_cron_cronicas' ) ) {
			wp_schedule_event( time(), 'ed_cada_10_min', 'ed_cron_cronicas' );
		}
	}

	/**
	 * @param array<string, mixed> $schedules
	 * @return array<string, mixed>
	 */
	public static function add_cron_schedule( array $schedules ): array {
		$schedules['ed_cada_10_min'] = array(
			'interval' => 600,
			/* translators: cron label */
			'display'  => __( 'Cada 10 minuts (DeportPress cròniques)', 'escuela-deportiva-core' ),
		);
		return $schedules;
	}

	public static function on_mvp_cerrado( int $partido_id, ?int $mvp_jugador_id ): void {
		unset( $mvp_jugador_id );
		self::encolar( $partido_id, false );
	}

	public static function on_evento_partido(
		int $evento_id,
		int $partido_id,
		string $tipo,
		$jugador_id,
		$equipo_id,
		$minuto
	): void {
		unset( $evento_id, $jugador_id, $equipo_id, $minuto );
		if ( 'fin_partido' !== $tipo ) {
			return;
		}
		$delay = (int) apply_filters( 'ed_cronica_fallback_delay_seconds', 12 * MINUTE_IN_SECONDS );
		wp_schedule_single_event( time() + $delay, 'ed_encolar_cronica_fallback', array( $partido_id ) );
	}

	public static function encolar( int $partido_id, bool $force = false ): void {
		if ( $partido_id <= 0 || 'partido' !== get_post_type( $partido_id ) ) {
			return;
		}
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$cronica = (string) get_field( ED_Torneos::PAR_CRONICA, $partido_id, false );
		if ( ! $force && '' !== trim( wp_strip_all_tags( $cronica ) ) ) {
			return;
		}

		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_cronicas_cola';
		$est   = $wpdb->get_var( $wpdb->prepare( "SELECT estado FROM {$tabla} WHERE partido_id = %d", $partido_id ) );
		if ( 'completada' === $est && ! $force ) {
			return;
		}

		$wpdb->replace(
			$tabla,
			array(
				'partido_id' => $partido_id,
				'estado'     => 'pendiente',
				'intentos'   => 0,
			),
			array( '%d', '%s', '%d' )
		);
	}

	/**
	 * Estat a la cua per a un partit (admin).
	 */
	public static function get_estado_cola( int $partido_id ): ?string {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_cronicas_cola';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_var( $wpdb->prepare( "SELECT estado FROM {$tabla} WHERE partido_id = %d", $partido_id ) );
		return $row ? (string) $row : null;
	}

	public static function procesar_cola(): void {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_cronicas_cola';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pendientes = $wpdb->get_results(
			"SELECT * FROM {$tabla}
			WHERE estado = 'pendiente' AND intentos < 3
			ORDER BY creado_en ASC
			LIMIT 5"
		);

		foreach ( $pendientes ? $pendientes : array() as $item ) {
			$wpdb->update(
				$tabla,
				array( 'estado' => 'procesando' ),
				array( 'id' => (int) $item->id ),
				array( '%s' ),
				array( '%d' )
			);

			$resultado = self::generar_cronica( (int) $item->partido_id );

			if ( ! empty( $resultado['ok'] ) ) {
				$wpdb->update(
					$tabla,
					array(
						'estado'       => 'completada',
						'procesado_en' => current_time( 'mysql' ),
						'error_msg'    => null,
					),
					array( 'id' => (int) $item->id ),
					array( '%s', '%s', '%s' ),
					array( '%d' )
				);
			} else {
				$nuevo = (int) $item->intentos + 1;
				$est   = $nuevo >= 3 ? 'error' : 'pendiente';
				$wpdb->update(
					$tabla,
					array(
						'estado'    => $est,
						'intentos'  => $nuevo,
						'error_msg' => isset( $resultado['error'] ) ? (string) $resultado['error'] : 'error',
					),
					array( 'id' => (int) $item->id ),
					array( '%s', '%d', '%s' ),
					array( '%d' )
				);
			}
			sleep( 2 );
		}
	}

	/**
	 * @return array{ok:bool, error?:string, post_id?:int, texto?:string}
	 */
	public static function generar_cronica( int $partido_id ): array {
		if ( ! defined( 'ED_OPENAI_API_KEY' ) || '' === (string) constant( 'ED_OPENAI_API_KEY' ) ) {
			return array( 'ok' => false, 'error' => 'ED_OPENAI_API_KEY no definida.' );
		}

		$datos = self::recopilar_datos_partido( $partido_id );
		if ( ! $datos ) {
			return array( 'ok' => false, 'error' => 'No s’han pogut recopilar dades del partit.' );
		}

		$prompt = self::construir_prompt( $datos );
		$resp   = self::llamar_openai( $prompt );
		if ( empty( $resp['ok'] ) ) {
			return $resp;
		}

		$texto = (string) $resp['texto'];
		if ( function_exists( 'update_field' ) ) {
			update_field( ED_Torneos::PAR_CRONICA, wp_kses_post( $texto ), $partido_id );
		}

		$post_id = self::crear_post_cronica( $partido_id, $texto, $datos );
		do_action( 'ed_cronica_generada', $partido_id, $post_id, $texto );

		return array( 'ok' => true, 'post_id' => $post_id, 'texto' => $texto );
	}

	/**
	 * @return array<string, mixed>|null
	 */
	private static function recopilar_datos_partido( int $partido_id ): ?array {
		if ( ! function_exists( 'get_field' ) ) {
			return null;
		}
		$local_id     = (int) get_field( ED_Torneos::PAR_EQ_LOCAL, $partido_id, false );
		$visitante_id = (int) get_field( ED_Torneos::PAR_EQ_VISITANTE, $partido_id, false );
		$torneo_id    = (int) get_field( ED_Torneos::PAR_TORNEO, $partido_id, false );
		if ( ! $local_id || ! $visitante_id ) {
			return null;
		}

		$goles_local     = (int) get_field( ED_Torneos::PAR_GOLES_LOCAL, $partido_id );
		$goles_visitante = (int) get_field( ED_Torneos::PAR_GOLES_VIS, $partido_id );
		$fase            = (string) ( get_field( ED_Torneos::PAR_FASE, $partido_id ) ?: 'partit' );
		$mvp_id          = (int) get_field( ED_Torneos::PAR_MVP, $partido_id, false );

		$eventos = ED_Partidos_Eventos::get_eventos( $partido_id );
		$narr    = array();
		foreach ( $eventos as $e ) {
			if ( in_array( (string) ( $e['tipo'] ?? '' ), array( 'gol', 'tarjeta_roja', 'tarjeta_amarilla', 'penalti', 'inicio', 'fin_primera', 'fin_partido' ), true ) ) {
				$narr[] = $e;
			}
		}

		$mvp_nom = null;
		if ( $mvp_id ) {
			$mvp_nom = trim( (string) get_field( 'ed_jugador_nombre', $mvp_id ) . ' ' . (string) get_field( 'ed_jugador_apellidos', $mvp_id ) );
		}

		$torneo_nom = $torneo_id ? get_post_field( 'post_title', $torneo_id ) : 'torneig';

		return array(
			'local'            => get_post_field( 'post_title', $local_id ),
			'visitante'        => get_post_field( 'post_title', $visitante_id ),
			'goles_local'      => $goles_local,
			'goles_visitante'  => $goles_visitante,
			'torneo'           => $torneo_nom,
			'fase'             => $fase,
			'mvp'              => $mvp_nom ? $mvp_nom : null,
			'eventos'          => array_values( $narr ),
			'diferencia'       => abs( $goles_local - $goles_visitante ),
			'ganador'          => $goles_local > $goles_visitante
				? get_post_field( 'post_title', $local_id )
				: ( $goles_visitante > $goles_local ? get_post_field( 'post_title', $visitante_id ) : null ),
		);
	}

	/**
	 * @param array<string, mixed> $d
	 */
	private static function construir_prompt( array $d ): string {
		$linea_eventos = array();
		foreach ( $d['eventos'] as $e ) {
			$min = ! empty( $e['minuto'] ) ? 'Min. ' . (int) $e['minuto'] . ': ' : '';
			$jug = ! empty( $e['jugador_nombre'] ) ? ' (' . $e['jugador_nombre'] . ')' : '';
			$eq  = ! empty( $e['equipo_nombre'] ) ? ' [' . $e['equipo_nombre'] . ']' : '';
			$linea_eventos[] = '- ' . $min . ( $e['tipo'] ?? '' ) . $jug . $eq;
		}
		$linea_eventos_str = implode( "\n", $linea_eventos );

		$tipo_part = 'partit normal';
		if ( (int) $d['diferencia'] >= 4 ) {
			$tipo_part = 'golejada';
		}
		if ( 0 === (int) $d['diferencia'] ) {
			$tipo_part = 'empat sense gols o amb gols';
		}

		$goleadores_str = '';
		$noms           = array();
		foreach ( $d['eventos'] as $e ) {
			if ( 'gol' === ( $e['tipo'] ?? '' ) && ! empty( $e['jugador_nombre'] ) ) {
				$noms[] = (string) $e['jugador_nombre'];
			}
		}
		if ( $noms ) {
			$goleadores_str = implode( ', ', array_unique( $noms ) );
		}

		$mvp_str = ! empty( $d['mvp'] ) ? 'MVP del partit: ' . $d['mvp'] . '.' : '';
		$hat     = self::GLOSARIO['hat_trick'];

		$ganador_txt = $d['ganador'] ? $d['ganador'] : 'empat';

		return <<<PROMPT
Ets un periodista esportiu especialitzat en futbol base i torneigs locals.
Escriu una crònica de partit en català, estil dinàmic, entre 200 i 350 paraules.
Frases curtes i paràgrafs clars. Sense asteriscos, sense markdown, sense llistes: només text narratiu.

DADES DEL PARTIT:
- Torneig: {$d['torneo']} ({$d['fase']})
- {$d['local']} {$d['goles_local']} - {$d['goles_visitante']} {$d['visitante']}
- Guanyador o empat: {$ganador_txt}
- Golejadors: {$goleadores_str}
- {$mvp_str}

CRONOLOGIA D’ESDEVENIMENTS:
{$linea_eventos_str}

VOCABULARI (quan escaigui):
- Hat-trick: {$hat}
- Tipus de resultat: {$tipo_part}

ESTRUCTURA:
1) Entrada: resultat i context
2) Desenvolupament: moments clau
3) Tancament: valoració; menciona l’MVP si n’hi ha

Escriu només la crònica, sense títol.
PROMPT;
	}

	/**
	 * @return array{ok:bool, error?:string, texto?:string}
	 */
	private static function llamar_openai( string $prompt ): array {
		$model = (string) apply_filters( 'ed_openai_cronica_model', 'gpt-4o-mini' );
		$res   = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . constant( 'ED_OPENAI_API_KEY' ),
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $model,
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => 'Ets un periodista esportiu expert en futbol base.',
							),
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'max_tokens'  => 600,
						'temperature' => 0.75,
					)
				),
				'timeout' => 45,
			)
		);

		if ( is_wp_error( $res ) ) {
			return array( 'ok' => false, 'error' => $res->get_error_message() );
		}
		$body = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( isset( $body['error']['message'] ) ) {
			return array( 'ok' => false, 'error' => (string) $body['error']['message'] );
		}
		$texto = $body['choices'][0]['message']['content'] ?? '';
		if ( ! is_string( $texto ) || '' === trim( $texto ) ) {
			return array( 'ok' => false, 'error' => 'Resposta buida de l’API.' );
		}
		return array( 'ok' => true, 'texto' => trim( $texto ) );
	}

	/**
	 * @param array<string, mixed> $datos
	 */
	private static function crear_post_cronica( int $partido_id, string $texto, array $datos ): int {
		$titulo = sprintf(
			'Crònica: %s %d-%d %s (%s)',
			$datos['local'],
			(int) $datos['goles_local'],
			(int) $datos['goles_visitante'],
			$datos['visitante'],
			$datos['torneo']
		);

		$cat_id = 0;
		$exists = term_exists( 'cronicas', 'category' );
		if ( $exists ) {
			$cat_id = is_array( $exists ) ? (int) $exists['term_id'] : (int) $exists;
		} else {
			$ins = wp_insert_term(
				'Cròniques',
				'category',
				array( 'slug' => 'cronicas' )
			);
			if ( ! is_wp_error( $ins ) && isset( $ins['term_id'] ) ) {
				$cat_id = (int) $ins['term_id'];
			}
		}

		$post_id = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => $titulo,
				'post_content'  => wpautop( wp_kses_post( $texto ) ),
				'post_category' => $cat_id ? array( $cat_id ) : array(),
				'meta_input'    => array(
					'_ed_partido_id' => $partido_id,
					'_ed_cronica_ia' => '1',
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			return 0;
		}
		update_post_meta( $partido_id, '_ed_cronica_post_id', (int) $post_id );
		return (int) $post_id;
	}

	public static function handle_admin_generar(): void {
		$partido_id = isset( $_GET['partido_id'] ) ? (int) $_GET['partido_id'] : 0;
		check_admin_referer( 'ed_cronica_' . $partido_id );
		if ( ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_escuela_deportiva' ) ) || $partido_id <= 0 ) {
			wp_die( esc_html__( 'No autoritzat.', 'escuela-deportiva-core' ) );
		}
		$force = isset( $_GET['force'] ) && '1' === $_GET['force'];
		if ( $force && function_exists( 'delete_field' ) ) {
			delete_field( ED_Torneos::PAR_CRONICA, $partido_id );
			delete_post_meta( $partido_id, '_ed_cronica_post_id' );
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . 'ed_cronicas_cola', array( 'partido_id' => $partido_id ), array( '%d' ) );
		}
		self::encolar( $partido_id, $force );
		wp_safe_redirect( admin_url( 'admin.php?page=ed-cronicas&mensaje=encolat' ) );
		exit;
	}
}
