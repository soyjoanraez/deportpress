<?php
/**
 * OneSignal REST i recordatoris de partit.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Push segmentat per tags.
 */
class ED_Push {

	private const API_URL = 'https://api.onesignal.com/notifications';

	public static function register_hooks(): void {
		add_action( 'ed_evento_partido_registrado', array( self::class, 'on_evento_partido' ), 10, 6 );
		add_action( 'ed_recordatorio_partido', array( self::class, 'notificar_recordatorio_partido' ), 10, 1 );
		add_action( 'save_post_partido', array( self::class, 'on_save_partido' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'maybe_enqueue_onesignal' ), 20 );
	}

	/**
	 * SDK web OneSignal v16 (només lectura; les push del servidor usen ED_ONESIGNAL_API_KEY).
	 */
	public static function maybe_enqueue_onesignal(): void {
		if ( is_admin() || ! defined( 'ED_ONESIGNAL_APP_ID' ) || '' === (string) constant( 'ED_ONESIGNAL_APP_ID' ) ) {
			return;
		}

		$app_id = (string) constant( 'ED_ONESIGNAL_APP_ID' );
		wp_register_script(
			'ed-onesignal-sdk',
			'https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js',
			array(),
			null,
			true
		);
		wp_script_add_data( 'ed-onesignal-sdk', 'strategy', 'defer' );
		wp_enqueue_script( 'ed-onesignal-sdk' );

		$local = in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
		$init  = array(
			'appId' => $app_id,
		);
		if ( $local ) {
			$init['allowLocalhostAsSecureOrigin'] = true;
		}

		wp_add_inline_script(
			'ed-onesignal-sdk',
			'window.OneSignalDeferred = window.OneSignalDeferred || [];',
			'before'
		);
		wp_add_inline_script(
			'ed-onesignal-sdk',
			sprintf(
				'OneSignalDeferred.push(async function(OneSignal){await OneSignal.init(%s);});',
				wp_json_encode( $init )
			),
			'after'
		);
	}

	public static function on_save_partido( int $post_id, WP_Post $post, bool $update ): void {
		if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		self::programar_recordatorio( $post_id );
	}

	/**
	 * @param array<int, array<string, mixed>> $tags filtres OneSignal
	 * @param array<string, mixed>            $data payload addicional
	 */
	public static function enviar( string $titulo, string $mensaje, array $tags, string $url = '', array $data = array() ): bool {
		if ( ! defined( 'ED_ONESIGNAL_APP_ID' ) || ! defined( 'ED_ONESIGNAL_API_KEY' ) ) {
			return false;
		}

		$payload = array(
			'app_id'   => ED_ONESIGNAL_APP_ID,
			'headings' => array( 'en' => $titulo, 'es' => $titulo ),
			'contents' => array( 'en' => $mensaje, 'es' => $mensaje ),
			'filters'  => $tags,
		);
		if ( $url ) {
			$payload['url'] = $url;
		}
		if ( ! empty( $data ) ) {
			$payload['data'] = $data;
		}

		$response = wp_remote_post(
			self::API_URL,
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Key ' . ED_ONESIGNAL_API_KEY,
				),
				'body'    => wp_json_encode( $payload ),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}
		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function filtro_partido( int $partido_id ): array {
		return array(
			array(
				'field'    => 'tag',
				'key'      => 'partido_' . $partido_id,
				'relation' => '=',
				'value'    => '1',
			),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function filtro_torneo( int $torneo_id ): array {
		return array(
			array(
				'field'    => 'tag',
				'key'      => 'torneo_' . $torneo_id,
				'relation' => '=',
				'value'    => '1',
			),
		);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function filtro_equipo( int $equipo_id ): array {
		return array(
			array(
				'field'    => 'tag',
				'key'      => 'equipo_' . $equipo_id,
				'relation' => '=',
				'value'    => '1',
			),
		);
	}

	public static function notificar_gol(
		int $partido_id,
		int $equipo_id,
		?int $jugador_id,
		int $minuto,
		int $goles_local,
		int $goles_visitante
	): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$equipo_nombre = get_post_field( 'post_title', $equipo_id );
		$jugador_nombre = '';
		if ( $jugador_id ) {
			$jugador_nombre = trim( (string) get_field( 'ed_jugador_nombre', $jugador_id ) . ' ' . (string) get_field( 'ed_jugador_apellidos', $jugador_id ) );
		}
		$local_id     = (int) get_field( ED_Torneos::PAR_EQ_LOCAL, $partido_id, false );
		$visitante_id = (int) get_field( ED_Torneos::PAR_EQ_VISITANTE, $partido_id, false );
		$local_nombre = get_post_field( 'post_title', $local_id );
		$visit_nombre = get_post_field( 'post_title', $visitante_id );
		$titulo       = sprintf( '⚽ %s', $equipo_nombre );
		$mensaje      = sprintf(
			'%s %d-%d %s | Min. %d%s',
			$local_nombre,
			$goles_local,
			$goles_visitante,
			$visit_nombre,
			$minuto,
			$jugador_nombre ? ' — ' . $jugador_nombre : ''
		);
		$url = ED_Torneos::get_url_partido( $partido_id );
		self::enviar( $titulo, $mensaje, self::filtro_partido( $partido_id ), $url, array( 'tipo' => 'gol', 'partido_id' => $partido_id ) );
		self::enviar( $titulo, $mensaje, self::filtro_equipo( $equipo_id ), $url );
	}

	public static function notificar_fin_primera( int $partido_id, int $goles_local, int $goles_visitante ): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$local_id     = (int) get_field( ED_Torneos::PAR_EQ_LOCAL, $partido_id, false );
		$visitante_id = (int) get_field( ED_Torneos::PAR_EQ_VISITANTE, $partido_id, false );
		self::enviar(
			__( '⏸ Descans', 'escuela-deportiva-core' ),
			sprintf(
				'%s %d-%d %s — %s',
				get_post_field( 'post_title', $local_id ),
				$goles_local,
				$goles_visitante,
				get_post_field( 'post_title', $visitante_id ),
				__( 'Fi del primer temps', 'escuela-deportiva-core' )
			),
			self::filtro_partido( $partido_id ),
			ED_Torneos::get_url_partido( $partido_id )
		);
	}

	public static function notificar_fin_partido( int $partido_id, int $goles_local, int $goles_visitante ): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$local_id     = (int) get_field( ED_Torneos::PAR_EQ_LOCAL, $partido_id, false );
		$visitante_id = (int) get_field( ED_Torneos::PAR_EQ_VISITANTE, $partido_id, false );
		$ln           = get_post_field( 'post_title', $local_id );
		$vn           = get_post_field( 'post_title', $visitante_id );
		if ( $goles_local > $goles_visitante ) {
			$res = sprintf( __( 'Guanya %s', 'escuela-deportiva-core' ), $ln );
		} elseif ( $goles_visitante > $goles_local ) {
			$res = sprintf( __( 'Guanya %s', 'escuela-deportiva-core' ), $vn );
		} else {
			$res = __( 'Empat', 'escuela-deportiva-core' );
		}
		self::enviar(
			__( '🏁 Fi del partit', 'escuela-deportiva-core' ),
			sprintf( '%s %d-%d %s — %s', $ln, $goles_local, $goles_visitante, $vn, $res ),
			self::filtro_partido( $partido_id ),
			ED_Torneos::get_url_partido( $partido_id ),
			array( 'tipo' => 'fin_partido', 'partido_id' => $partido_id )
		);
	}

	public static function notificar_apertura_mvp( int $partido_id ): void {
		$url = home_url( '/votar-mvp/?partido=' . $partido_id );
		self::enviar(
			__( '🏆 Vota l’MVP!', 'escuela-deportiva-core' ),
			__( 'El partit ha acabat. Qui ha estat el millor jugador?', 'escuela-deportiva-core' ),
			self::filtro_partido( $partido_id ),
			$url,
			array( 'tipo' => 'mvp_votacion', 'partido_id' => $partido_id )
		);
	}

	public static function notificar_tarjeta_roja( int $partido_id, ?int $jugador_id, int $equipo_id, int $minuto ): void {
		$nom = __( 'Un jugador', 'escuela-deportiva-core' );
		if ( $jugador_id && function_exists( 'get_field' ) ) {
			$nom = trim( (string) get_field( 'ed_jugador_nombre', $jugador_id ) . ' ' . (string) get_field( 'ed_jugador_apellidos', $jugador_id ) );
		}
		self::enviar(
			__( '🟥 Targeta roja', 'escuela-deportiva-core' ),
			sprintf(
				'%s (%s) — min. %d',
				$nom,
				get_post_field( 'post_title', $equipo_id ),
				$minuto
			),
			self::filtro_partido( $partido_id ),
			ED_Torneos::get_url_partido( $partido_id )
		);
	}

	public static function notificar_recordatorio_partido( int $partido_id ): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$local_id     = (int) get_field( ED_Torneos::PAR_EQ_LOCAL, $partido_id, false );
		$visitante_id = (int) get_field( ED_Torneos::PAR_EQ_VISITANTE, $partido_id, false );
		$torneo_id    = (int) get_field( ED_Torneos::PAR_TORNEO, $partido_id, false );
		if ( ! $local_id || ! $visitante_id ) {
			return;
		}
		$filtros = array_merge(
			self::filtro_torneo( $torneo_id ),
			array( array( 'operator' => 'OR' ) ),
			self::filtro_equipo( $local_id ),
			array( array( 'operator' => 'OR' ) ),
			self::filtro_equipo( $visitante_id )
		);
		self::enviar(
			__( '⏰ Partit en 15 min', 'escuela-deportiva-core' ),
			sprintf(
				'%s vs %s',
				get_post_field( 'post_title', $local_id ),
				get_post_field( 'post_title', $visitante_id )
			),
			$filtros,
			ED_Torneos::get_url_partido( $partido_id )
		);
	}

	public static function on_evento_partido(
		int $evento_id,
		int $partido_id,
		string $tipo,
		?int $jugador_id,
		?int $equipo_id,
		?int $minuto
	): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$goles_local     = (int) get_field( ED_Torneos::PAR_GOLES_LOCAL, $partido_id );
		$goles_visitante = (int) get_field( ED_Torneos::PAR_GOLES_VIS, $partido_id );

		switch ( $tipo ) {
			case 'gol':
				if ( $equipo_id ) {
					self::notificar_gol( $partido_id, $equipo_id, $jugador_id, (int) ( $minuto ?? 0 ), $goles_local, $goles_visitante );
				}
				if ( $jugador_id ) {
					ED_Rankings::incrementar( $jugador_id, 'goles', $partido_id );
				}
				break;
			case 'tarjeta_roja':
				if ( $equipo_id ) {
					self::notificar_tarjeta_roja( $partido_id, $jugador_id, $equipo_id, (int) ( $minuto ?? 0 ) );
				}
				if ( $jugador_id ) {
					ED_Rankings::incrementar( $jugador_id, 'rojas', $partido_id );
				}
				break;
			case 'tarjeta_amarilla':
				if ( $jugador_id ) {
					ED_Rankings::incrementar( $jugador_id, 'amarillas', $partido_id );
				}
				break;
			case 'fin_primera':
				self::notificar_fin_primera( $partido_id, $goles_local, $goles_visitante );
				break;
			case 'fin_partido':
				self::notificar_fin_partido( $partido_id, $goles_local, $goles_visitante );
				$delay = (int) apply_filters( 'ed_mvp_apertura_delay_seconds', 30 );
				wp_schedule_single_event( time() + $delay, 'ed_abrir_votacion_mvp', array( $partido_id ) );
				break;
		}
	}

	public static function programar_recordatorio( int $partido_id ): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}
		$fecha_hora = (string) get_field( ED_Torneos::PAR_FECHA_HORA, $partido_id );
		if ( '' === $fecha_hora ) {
			return;
		}
		$timestamp = strtotime( $fecha_hora ) - ( 15 * MINUTE_IN_SECONDS );
		if ( $timestamp <= time() ) {
			return;
		}
		$hook = 'ed_recordatorio_partido';
		$args = array( $partido_id );
		$next = wp_next_scheduled( $hook, $args );
		if ( $next ) {
			wp_unschedule_event( $next, $hook, $args );
		}
		wp_schedule_single_event( $timestamp, $hook, $args );
	}
}
