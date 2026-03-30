<?php
/**
 * Correu electrònic del mòdul de pagaments.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plantilles HTML DeportPress.
 */
class ED_Emails {

	/**
	 * Correu del primer adult del nucli o admin.
	 */
	private static function get_email_adulto( int $nucleo_id ): string {
		$uid = ED_Nucleo_Repository::get_first_adult_user_id( $nucleo_id );
		if ( $uid > 0 ) {
			$user = get_userdata( $uid );
			if ( $user && is_email( $user->user_email ) ) {
				return $user->user_email;
			}
		}
		return (string) get_option( 'admin_email' );
	}

	/**
	 * Envia HTML.
	 */
	private static function send( string $to, string $subject, string $body_html ): void {
		if ( ! is_email( $to ) ) {
			return;
		}
		$club = self::get_club_name();
		add_filter( 'wp_mail_content_type', array( __CLASS__, 'mail_content_type_html' ) );
		wp_mail(
			$to,
			'[' . $club . '] ' . $subject,
			self::wrap_template( $body_html )
		);
		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'mail_content_type_html' ) );
	}

	/**
	 * Nom del club des de la configuració (administrable sense modificar codi).
	 */
	private static function get_club_name(): string {
		$name = (string) get_option( 'ed_nombre_club', '' );
		return '' !== $name ? $name : get_bloginfo( 'name' );
	}

	/**
	 * @return string
	 */
	public static function mail_content_type_html(): string {
		return 'text/html';
	}

	private static function wrap_template( string $content ): string {
		$club = esc_html( self::get_club_name() );
		return sprintf(
			'<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">
				<div style="background:#1A1A1A;padding:24px 32px;text-align:center;">
					<span style="color:#FFD600;font-size:28px;font-weight:700;letter-spacing:2px;">%s</span>
				</div>
				<div style="padding:32px;">%s</div>
				<div style="background:#f5f5f5;padding:16px 32px;text-align:center;font-size:12px;color:#757575;">%s · Ondara, Alacant</div>
			</div>',
			$club,
			$content,
			$club
		);
	}

	public static function enviar_confirmacion_pago( int $nucleo_id, int $plazo_id, int $order_id ): void {
		global $wpdb;
		$plazo = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ed_pagos_plazos WHERE id = %d",
				$plazo_id
			)
		);
		if ( ! $plazo ) {
			return;
		}
		$to      = self::get_email_adulto( $nucleo_id );
		$deporte = get_post_field( 'post_title', (int) $plazo->deporte_id );
		$jugador = '';
		if ( function_exists( 'get_field' ) ) {
			$jugador = trim( (string) get_field( 'ed_jugador_nombre', (int) $plazo->jugador_id ) . ' ' . (string) get_field( 'ed_jugador_apellidos', (int) $plazo->jugador_id ) );
		}
		if ( '' === $jugador ) {
			$jugador = get_post_field( 'post_title', (int) $plazo->jugador_id );
		}
		$url_pdf = '#';
		if ( $order_id > 0 && function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$url_pdf = $order->get_view_order_url();
			}
		}

		$body = sprintf(
			'<h2 style="color:#1A1A1A;">%1$s</h2>
			<p>%2$s <strong>%3$d</strong></p>
			<table style="width:100%%;border-collapse:collapse;">
				<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#757575;">%4$s</td><td style="padding:8px;border-bottom:1px solid #eee;"><strong>%5$s</strong></td></tr>
				<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#757575;">%6$s</td><td style="padding:8px;border-bottom:1px solid #eee;">%7$s</td></tr>
				<tr><td style="padding:8px;border-bottom:1px solid #eee;color:#757575;">%8$s</td><td style="padding:8px;border-bottom:1px solid #eee;"><strong>%9$.2f €</strong></td></tr>
			</table>
			<p style="margin-top:24px;"><a href="%10$s" style="background:#1A1A1A;color:#FFD600;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:700;">%11$s</a></p>',
			esc_html__( 'Pagament confirmat', 'escuela-deportiva-core' ),
			esc_html__( 'Hem rebut el pagament del plaç', 'escuela-deportiva-core' ),
			(int) $plazo->plazo,
			esc_html__( 'Esport', 'escuela-deportiva-core' ),
			esc_html( $deporte ),
			esc_html__( 'Jugador', 'escuela-deportiva-core' ),
			esc_html( $jugador ),
			esc_html__( 'Import', 'escuela-deportiva-core' ),
			(float) $plazo->importe,
			esc_url( $url_pdf ),
			esc_html__( 'Veure comanda', 'escuela-deportiva-core' )
		);

		self::send( $to, __( 'Pagament confirmat', 'escuela-deportiva-core' ) . ' — ' . $deporte, $body );
	}

	/**
	 * Recordatori si encara no s’ha enviat aquest tipus per aquest plazo (opció transitoria).
	 */
	public static function enviar_recordatorio( int $nucleo_id, int $plazo_id, int $dias ): void {
		global $wpdb;
		$plazo = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ed_pagos_plazos WHERE id = %d",
				$plazo_id
			)
		);
		if ( ! $plazo || 'pendiente' !== $plazo->estado ) {
			return;
		}
		$key = 'ed_rem_' . $plazo_id . '_' . $dias . '_' . (string) $plazo->fecha_prevista;
		if ( get_transient( $key ) ) {
			return;
		}

		$to      = self::get_email_adulto( $nucleo_id );
		$deporte = get_post_field( 'post_title', (int) $plazo->deporte_id );

		$body = sprintf(
			'<h2 style="color:#1A1A1A;">%1$s</h2>
			<p>%2$s <strong>%3$s</strong> %4$s <strong>%5$d</strong> %6$s <strong>%7$s</strong>.</p>
			<p>%8$s: <strong>%9$.2f €</strong></p>',
			esc_html__( 'Recordatori de pagament', 'escuela-deportiva-core' ),
			esc_html__( 'El cobrament del plaç 2 de', 'escuela-deportiva-core' ),
			esc_html( $deporte ),
			esc_html__( 'està previst d’ací a', 'escuela-deportiva-core' ),
			$dias,
			esc_html__( 'dies (data límit', 'escuela-deportiva-core' ),
			esc_html( date_i18n( 'd/m/Y', strtotime( (string) $plazo->fecha_prevista ) ) ),
			esc_html__( 'Import', 'escuela-deportiva-core' ),
			(float) $plazo->importe
		);

		self::send(
			$to,
			sprintf(
				/* translators: %d: days */
				__( 'Recordatori: pagament en %d dies', 'escuela-deportiva-core' ),
				$dias
			) . ' — ' . $deporte,
			$body
		);
		set_transient( $key, 1, 5 * DAY_IN_SECONDS );
	}

	public static function enviar_pago_fallido( int $nucleo_id, int $plazo_id ): void {
		global $wpdb;
		$plazo = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ed_pagos_plazos WHERE id = %d",
				$plazo_id
			)
		);
		if ( ! $plazo ) {
			return;
		}
		$to      = self::get_email_adulto( $nucleo_id );
		$deporte = get_post_field( 'post_title', (int) $plazo->deporte_id );

		$body = sprintf(
			'<h2 style="color:#B71C1C;">%1$s</h2>
			<p>%2$s <strong>%3$s</strong>.</p>
			<p>%4$s</p>',
			esc_html__( 'Pagament no processat', 'escuela-deportiva-core' ),
			esc_html__( 'No hem pogut cobrar el plaç 2 de', 'escuela-deportiva-core' ),
			esc_html( $deporte ),
			esc_html__( 'Tornarem a intentar-ho automàticament. Si cal, actualitza el mètode de pagament des del panel familiar.', 'escuela-deportiva-core' )
		);

		self::send( $to, __( 'Pagament fallit', 'escuela-deportiva-core' ) . ' — ' . $deporte, $body );
	}

	public static function enviar_aviso_caducidad( int $nucleo_id, int $plazo_id ): void {
		global $wpdb;
		$plazo = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ed_pagos_plazos WHERE id = %d",
				$plazo_id
			)
		);
		if ( ! $plazo ) {
			return;
		}
		$to      = self::get_email_adulto( $nucleo_id );
		$deporte = get_post_field( 'post_title', (int) $plazo->deporte_id );

		$body = sprintf(
			'<h2 style="color:#E65100;">%1$s</h2>
			<p>%2$s <strong>%3$s</strong>. %4$s</p>',
			esc_html__( 'Avís: targeta caducada o pròxima a caducar', 'escuela-deportiva-core' ),
			esc_html__( 'Hem detectat que la targeta vinculada per al pagament de', 'escuela-deportiva-core' ),
			esc_html( $deporte ),
			esc_html__( 'ja ha caducat o caduca aquest mes. Si us plau, actualitza-la al TEU PANEL FAMILIAR per evitar que falli el cobrament, gràcies!', 'escuela-deportiva-core' )
		);

		self::send( $to, __( 'Atenció: Targeta caducada', 'escuela-deportiva-core' ) . ' — ' . $deporte, $body );
	}

	public static function enviar_impago_definitivo( int $nucleo_id, int $plazo_id ): void {
		global $wpdb;
		$plazo   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ed_pagos_plazos WHERE id = %d",
				$plazo_id
			)
		);
		$deporte = $plazo ? get_post_field( 'post_title', (int) $plazo->deporte_id ) : '';

		$body_fam = sprintf(
			'<h2 style="color:#B71C1C;">%1$s</h2>
			<p>%2$s <strong>%3$s</strong>.</p>',
			esc_html__( 'Quota no abonada', 'escuela-deportiva-core' ),
			esc_html__( 'Després de diversos intents no hem pogut cobrar la quota de', 'escuela-deportiva-core' ),
			esc_html( $deporte )
		);
		self::send( self::get_email_adulto( $nucleo_id ), __( 'Avís: quota impagada', 'escuela-deportiva-core' ) . ' — ' . $deporte, $body_fam );

		$admin = (string) get_option( 'admin_email' );
		self::send(
			$admin,
			'[IMPAGO] Plaç ID ' . $plazo_id,
			sprintf(
				'<p>Plaç <strong>%1$d</strong> (%2$s, nucli %3$d) cancel·lat després dels intents.</p>',
				$plazo_id,
				esc_html( $deporte ),
				$nucleo_id
			)
		);
	}

	public static function enviar_alerta_gestor_redsys( int $plazo_id ): void {
		self::send(
			(string) get_option( 'admin_email' ),
			'[ACCIÓ] Cobrament manual Redsys — Plaç ' . $plazo_id,
			'<p>' . esc_html__( 'El plaç requereix gestió manual Redsys.', 'escuela-deportiva-core' ) . '</p>'
		);
	}
}
