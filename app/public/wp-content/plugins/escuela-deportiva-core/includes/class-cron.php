<?php
/**
 * Tasques programades: cobraments 2n plaç i recordatoris.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cron DeportPress.
 */
class ED_Cron {

	public function register(): void {
		add_filter( 'cron_schedules', array( __CLASS__, 'add_sixhourly_schedule' ) );

		add_action( 'ed_cron_cobro_plazos', array( __CLASS__, 'run_cobro_plazos' ) );
		add_action( 'ed_cron_recordatorios', array( __CLASS__, 'run_recordatorios' ) );
		add_action( 'ed_cron_limpiar_basura', array( __CLASS__, 'run_limpiar_basura' ) );
		add_action( 'ed_cron_cronicas', array( 'ED_IA_Cronicas', 'procesar_cola' ) );
		add_action( 'ed_cron_sync_federacion', array( 'ED_Sync_Federacion', 'sincronizar' ) );

		if ( class_exists( 'ED_IA_Cronicas' ) ) {
			ED_IA_Cronicas::registrar_cron();
		}

		if ( ! wp_next_scheduled( 'ed_cron_cobro_plazos' ) ) {
			wp_schedule_event( time(), 'twicedaily', 'ed_cron_cobro_plazos' );
		}
		if ( ! wp_next_scheduled( 'ed_cron_recordatorios' ) ) {
			wp_schedule_event( time(), 'daily', 'ed_cron_recordatorios' );
		}
		if ( ! wp_next_scheduled( 'ed_cron_limpiar_basura' ) ) {
			wp_schedule_event( time(), 'daily', 'ed_cron_limpiar_basura' );
		}
		if ( ! wp_next_scheduled( 'ed_cron_sync_federacion' ) ) {
			wp_schedule_event( time(), 'hourly', 'ed_cron_sync_federacion' );
		}
	}

	/**
	 * Interval de 6 hores per a la sync FFCV.
	 *
	 * @param array<string, array{interval:int, display:string}> $schedules
	 * @return array<string, array{interval:int, display:string}>
	 */
	public static function add_sixhourly_schedule( array $schedules ): array {
		$schedules['sixhourly'] = array(
			'interval' => 6 * HOUR_IN_SECONDS,
			'display'  => __( 'Cada 6 hores (DeportPress FFCV)', 'escuela-deportiva-core' ),
		);
		return $schedules;
	}

	public static function run_cobro_plazos(): void {
		global $wpdb;

		$hoy = wp_date( 'Y-m-d' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$plazos = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ed_pagos_plazos
				WHERE plazo = 2
				AND estado IN ('pendiente','fallido')
				AND fecha_prevista <= %s
				AND token_pago IS NOT NULL AND token_pago != ''
				ORDER BY fecha_prevista ASC
				LIMIT 50",
				$hoy
			)
		);

		foreach ( $plazos as $plazo ) {
			self::intentar_cobro_automatico( $plazo );
		}
	}

	/**
	 * @param object $plazo Fila ed_pagos_plazos.
	 */
	private static function intentar_cobro_automatico( object $plazo ): void {
		global $wpdb;

		$tz       = wp_timezone();
		$prevista = new DateTimeImmutable( (string) $plazo->fecha_prevista . ' 00:00:00', $tz );
		$hoy      = new DateTimeImmutable( 'today', $tz );
		$dias     = (int) floor( ( $hoy->getTimestamp() - $prevista->getTimestamp() ) / DAY_IN_SECONDS );

		if ( $dias > 2 ) {
			$wpdb->update(
				$wpdb->prefix . 'ed_pagos_plazos',
				array( 'estado' => 'cancelado' ),
				array( 'id' => (int) $plazo->id ),
				array( '%s' ),
				array( '%d' )
			);
			ED_Emails::enviar_impago_definitivo( (int) $plazo->nucleo_id, (int) $plazo->id );
			return;
		}

		if ( 'stripe' === $plazo->pasarela && class_exists( 'ED_Pagos_Stripe' ) ) {
			$parts = explode( '|', (string) $plazo->token_pago, 2 );
			$pm    = $parts[0] ?? '';
			$cus   = $parts[1] ?? '';
			if ( ! $pm || ! $cus ) {
				return;
			}
			$resultado = ED_Pagos_Stripe::cobrar_off_session( (float) $plazo->importe, $pm, $cus );
			if ( ! empty( $resultado['ok'] ) ) {
				ED_Pagos::marcar_pagado(
					(int) $plazo->id,
					'stripe',
					(string) ( $resultado['payment_intent_id'] ?? '' )
				);
			} else {
				ED_Pagos::marcar_fallido( (int) $plazo->id, (string) ( $resultado['error'] ?? 'stripe' ) );
			}
			return;
		}

		if ( 'redsys' === $plazo->pasarela ) {
			ED_Emails::enviar_alerta_gestor_redsys( (int) $plazo->id );
		}
	}

	public static function run_recordatorios(): void {
		global $wpdb;

		foreach ( array( 10, 7, 1 ) as $dias ) {
			$fecha_objetivo = ( new DateTimeImmutable( 'today', wp_timezone() ) )
				->modify( '+' . $dias . ' days' )
				->format( 'Y-m-d' );
			$plazos         = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$wpdb->prefix}ed_pagos_plazos
					WHERE estado = 'pendiente'
					AND fecha_prevista = %s",
					$fecha_objetivo
				)
			);
			foreach ( $plazos as $plazo ) {
				if ( 10 === $dias ) {
					if ( 'stripe' === $plazo->pasarela && $plazo->token_pago && class_exists( 'ED_Pagos_Stripe' ) ) {
						$parts = explode( '|', (string) $plazo->token_pago, 2 );
						$pm    = $parts[0] ?? '';
						if ( $pm && ED_Pagos_Stripe::check_tarjeta_caducada( $pm ) ) {
							ED_Emails::enviar_aviso_caducidad( (int) $plazo->nucleo_id, (int) $plazo->id );
						} else {
							ED_Emails::enviar_recordatorio( (int) $plazo->nucleo_id, (int) $plazo->id, $dias );
						}
					}
				} else {
					ED_Emails::enviar_recordatorio( (int) $plazo->nucleo_id, (int) $plazo->id, $dias );
				}
			}
		}
	}

	public static function run_limpiar_basura(): void {
		global $wpdb;
		// 1. Acuses de recibo de correo de hace más de 120 días
		$fecha_120d = gmdate( 'Y-m-d H:i:s', time() - ( 120 * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}ed_msg_recepciones
				WHERE leido = 1 AND leido_en < %s",
				$fecha_120d
			)
		);
		// 2. Colas de IA completadas hace más de 30 días
		$fecha_30d = gmdate( 'Y-m-d H:i:s', time() - ( 30 * DAY_IN_SECONDS ) );
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->prefix}ed_cronicas_cola
				WHERE estado IN ('completada', 'error') AND procesado_en < %s",
				$fecha_30d
			)
		);
	}
}
