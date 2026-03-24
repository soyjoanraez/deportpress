<?php
/**
 * Entrades amb QR (Fase 6).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Generació, validació i estadístiques d’entrades.
 */
class ED_Entradas_QR {

	/**
	 * @param int $order_id ID de comanda WooCommerce.
	 */
	public static function generar_desde_order( int $order_id ): void {
		if ( ! function_exists( 'wc_get_order' ) ) {
			return;
		}
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		global $wpdb;
		$tabla        = $wpdb->prefix . 'ed_entradas';
		$alguna_nueva = false;

		$nucleo_id = (int) $order->get_meta( '_ed_nucleo_id' );
		$nucleo_id = $nucleo_id > 0 ? $nucleo_id : null;

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();
			if ( ! $product ) {
				continue;
			}
			$sku = (string) $product->get_sku();
			if ( ! str_starts_with( $sku, 'entrada-' ) ) {
				continue;
			}
			$torneo_id = (int) str_replace( 'entrada-', '', $sku );
			if ( $torneo_id <= 0 ) {
				continue;
			}

			$cantidad   = (int) $item->get_quantity();
			$item_id_db = (int) $item_id;
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$existentes = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE wc_order_id = %d AND wc_order_item_id = %d", $order_id, $item_id_db ) );
			$pendientes = max( 0, $cantidad - $existentes );
			if ( $pendientes <= 0 ) {
				continue;
			}

			$nombre = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
			$email  = (string) $order->get_billing_email();
			$uid    = $order->get_customer_id() ? (int) $order->get_customer_id() : null;
			$precio = $cantidad > 0 ? (float) $item->get_total() / $cantidad : 0.0;

			for ( $i = 0; $i < $pendientes; $i++ ) {
				$entrada_id = self::crear_entrada(
					$torneo_id,
					$order_id,
					$item_id_db,
					$uid,
					$nucleo_id,
					$nombre,
					$email,
					$precio
				);
				if ( $entrada_id ) {
					$alguna_nueva = true;
					ED_Rifa::asignar_numeros( $torneo_id, (int) $entrada_id );
				}
			}
		}

		if ( $alguna_nueva ) {
			self::enviar_email_entradas( $order_id );
		}
	}

	/**
	 * @return int|false ID inserit.
	 */
	private static function crear_entrada(
		int $torneo_id,
		int $order_id,
		int $item_id,
		?int $user_id,
		?int $nucleo_id,
		string $nombre,
		string $email,
		float $precio
	) {
		global $wpdb;

		$token = self::generar_token();

		$resultado = $wpdb->insert(
			$wpdb->prefix . 'ed_entradas',
			array(
				'torneo_id'        => $torneo_id,
				'user_id'          => $user_id,
				'nucleo_id'        => $nucleo_id,
				'wc_order_id'      => $order_id,
				'wc_order_item_id' => $item_id,
				'qr_token'         => $token,
				'estado'           => 'valida',
				'nombre_titular'   => sanitize_text_field( $nombre ),
				'email_titular'    => sanitize_email( $email ),
				'precio'           => round( $precio, 2 ),
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%f' )
		);

		return $resultado ? (int) $wpdb->insert_id : false;
	}

	private static function generar_token(): string {
		return bin2hex( random_bytes( 16 ) );
	}

	/**
	 * @return array{ok:bool,error?:string,codigo?:string,usado_en?:string,entrada_id?:int,titular?:string,torneo?:string,numeros_rifa?:int[],mensaje?:string}
	 */
	public static function validar( string $token, int $staff_user_id ): array {
		global $wpdb;
		$token = sanitize_text_field( $token );

		$entrada = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ed_entradas WHERE qr_token = %s",
				$token
			)
		);

		if ( ! $entrada ) {
			return array(
				'ok'     => false,
				'error'  => __( 'Entrada no trobada.', 'escuela-deportiva-core' ),
				'codigo' => 'not_found',
			);
		}

		if ( 'usada' === $entrada->estado ) {
			return array(
				'ok'       => false,
				'error'    => __( 'Aquesta entrada ja s’ha utilitzat.', 'escuela-deportiva-core' ),
				'codigo'   => 'already_used',
				'usado_en' => $entrada->usado_en,
			);
		}

		if ( 'cancelada' === $entrada->estado ) {
			return array(
				'ok'     => false,
				'error'  => __( 'Entrada cancel·lada.', 'escuela-deportiva-core' ),
				'codigo' => 'cancelled',
			);
		}

		if ( 'valida' !== $entrada->estado ) {
			return array(
				'ok'     => false,
				'error'  => __( 'Estat d’entrada no vàlid.', 'escuela-deportiva-core' ),
				'codigo' => 'invalid_state',
			);
		}

		$wpdb->update(
			$wpdb->prefix . 'ed_entradas',
			array(
				'estado'    => 'usada',
				'usado_en'  => current_time( 'mysql' ),
				'usado_por' => $staff_user_id,
			),
			array( 'id' => (int) $entrada->id ),
			array( '%s', '%s', '%d' ),
			array( '%d' )
		);

		$torneo_nombre = get_post_field( 'post_title', (int) $entrada->torneo_id );

		return array(
			'ok'           => true,
			'entrada_id'   => (int) $entrada->id,
			'titular'      => $entrada->nombre_titular,
			'torneo'       => $torneo_nombre ? (string) $torneo_nombre : '',
			'numeros_rifa' => ED_Rifa::get_numeros_entrada( (int) $entrada->id ),
			'mensaje'      => __( 'Entrada vàlida — Benvingut/da', 'escuela-deportiva-core' ),
		);
	}

	public static function enviar_email_entradas( int $order_id ): void {
		global $wpdb;

		$entradas = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}ed_entradas WHERE wc_order_id = %d ORDER BY id ASC",
				$order_id
			)
		);

		if ( empty( $entradas ) ) {
			return;
		}

		$email  = $entradas[0]->email_titular;
		$nombre = $entradas[0]->nombre_titular;
		$torneo = get_post_field( 'post_title', (int) $entradas[0]->torneo_id );

		if ( ! is_email( $email ) ) {
			return;
		}

		$entradas_html = '';
		foreach ( $entradas as $e ) {
			$qr_url       = rest_url( 'ed/v1/entrada/' . rawurlencode( $e->qr_token ) . '/qr' );
			$numeros_rifa = ED_Rifa::get_numeros_entrada( (int) $e->id );
			$numeros_str  = ! empty( $numeros_rifa )
				? ' · ' . __( 'Números rifa:', 'escuela-deportiva-core' ) . ' <strong>' . esc_html( implode( ', ', $numeros_rifa ) ) . '</strong>'
				: '';

			$entradas_html .= sprintf(
				'<div style="border:1px solid #eee;border-radius:8px;padding:20px;margin-bottom:16px;text-align:center;">
				<p style="font-size:12px;color:#757575;margin:0 0 12px;">ID: %d%s</p>
				<img src="%s" alt="QR" style="width:180px;height:180px;">
				<p style="font-size:12px;color:#757575;margin:12px 0 0;">%s</p>
				</div>',
				(int) $e->id,
				$numeros_str,
				esc_url( $qr_url ),
				esc_html__( 'Mostra aquest QR a l’entrada de l’esdeveniment.', 'escuela-deportiva-core' )
			);
		}

		$asunto = sprintf(
			/* translators: %s: tournament title */
			__( '[Escoles Esportives Ondara] La teva entrada per a %s', 'escuela-deportiva-core' ),
			$torneo
		);
		$cuerpo = sprintf(
			'<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">
			<div style="background:#1A1A1A;padding:24px 32px;text-align:center;">
			<span style="color:#FFD600;font-size:28px;font-weight:700;">ESCOLES ESPORTIVES ONDARA</span>
			</div>
			<div style="padding:32px;">
			<h2 style="color:#1A1A1A;">%s</h2>
			<p>%s <strong>%s</strong>.</p>
			%s
			<p style="color:#757575;font-size:13px;">%s</p>
			</div></div>',
			esc_html( sprintf( __( 'Hola %s', 'escuela-deportiva-core' ), $nombre ) ),
			esc_html__( 'Aquí tens la teva entrada per a', 'escuela-deportiva-core' ),
			esc_html( (string) $torneo ),
			$entradas_html,
			esc_html__( 'Recorda tenir el QR visible al mòbil en arribar.', 'escuela-deportiva-core' )
		);

		add_filter( 'wp_mail_content_type', array( __CLASS__, 'filter_mail_html' ) );
		wp_mail( $email, $asunto, $cuerpo );
		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'filter_mail_html' ) );
	}

	public static function filter_mail_html(): string {
		return 'text/html';
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_entradas_usuario( int $user_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.*, p.post_title AS torneo_nombre
				FROM {$wpdb->prefix}ed_entradas e
				LEFT JOIN {$wpdb->posts} p ON p.ID = e.torneo_id
				WHERE e.user_id = %d
				ORDER BY e.creado_en DESC",
				$user_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array{total:int,validas:int,usadas:int,ingresos:float}
	 */
	public static function get_stats_torneo( int $torneo_id ): array {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_entradas';
		return array(
			'total'    => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE torneo_id = %d AND estado != 'cancelada'", $torneo_id ) ),
			'validas'  => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE torneo_id = %d AND estado = 'valida'", $torneo_id ) ),
			'usadas'   => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$tabla} WHERE torneo_id = %d AND estado = 'usada'", $torneo_id ) ),
			'ingresos' => (float) $wpdb->get_var( $wpdb->prepare( "SELECT COALESCE(SUM(precio),0) FROM {$tabla} WHERE torneo_id = %d AND estado != 'cancelada'", $torneo_id ) ),
		);
	}

	public static function get_qr_url( string $token ): string {
		$data = add_query_arg( 'token', rawurlencode( $token ), home_url( '/validar-entrada/' ) );
		return 'https://chart.googleapis.com/chart?chs=300x300&cht=qr&chl=' . rawurlencode( $data ) . '&choe=UTF-8';
	}
}
