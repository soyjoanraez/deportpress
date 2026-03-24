<?php
/**
 * Gestió de plazos de pagament 50/50 i integració WooCommerce.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plazos custom (taula ed_pagos_plazos).
 */
class ED_Pagos {

	/**
	 * Crea dos plazos per jugador / esport.
	 *
	 * @return array{plazo1_id?:int,plazo2_id?:int,error?:string}
	 */
	public static function crear_plazos(
		int $nucleo_id,
		int $jugador_id,
		int $deporte_id,
		string $pasarela,
		?string $token_pago = null
	): array {
		global $wpdb;

		if ( ! function_exists( 'get_field' ) ) {
			return array( 'error' => 'ACF no disponible.' );
		}

		// Guard d'idempotència: comprova si ja existeixen plazos per a aquesta combinació.
		// Evita duplicats financers si el botó de pagament es prem dues vegades.
		$tabla   = $wpdb->prefix . 'ed_pagos_plazos';
		$existents = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$tabla}
				WHERE nucleo_id = %d AND jugador_id = %d AND deporte_id = %d",
				$nucleo_id,
				$jugador_id,
				$deporte_id
			)
		);
		if ( $existents > 0 ) {
			return array( 'error' => __( 'Ja existeixen plazos per a aquest jugador i esport.', 'escuela-deportiva-core' ) );
		}

		$importe_total = (float) get_field( 'ed_dep_importe_total', $deporte_id );
		$fecha_plazo1  = get_field( 'ed_dep_fecha_plazo1', $deporte_id );
		$fecha_plazo2  = get_field( 'ed_dep_fecha_plazo2', $deporte_id );

		if ( $importe_total <= 0 || empty( $fecha_plazo1 ) || empty( $fecha_plazo2 ) ) {
			return array( 'error' => __( 'Esport sense configuració de pagaments completa.', 'escuela-deportiva-core' ) );
		}

		$mitad = round( $importe_total / 2, 2 );
		$now   = current_time( 'mysql' );
		$ids   = array();

		$rows = array(
			array(
				'plazo'   => 1,
				'importe' => $mitad,
				'fecha'   => $fecha_plazo1,
				'token'   => null,
			),
			array(
				'plazo'   => 2,
				'importe' => $mitad,
				'fecha'   => $fecha_plazo2,
				'token'   => $token_pago,
			),
		);

		foreach ( $rows as $p ) {
			$wpdb->insert(
				$tabla,
				array(
					'nucleo_id'      => $nucleo_id,
					'jugador_id'     => $jugador_id,
					'deporte_id'     => $deporte_id,
					'plazo'          => $p['plazo'],
					'importe'        => $p['importe'],
					'estado'         => 'pendiente',
					'fecha_prevista' => $p['fecha'],
					'pasarela'       => $pasarela,
					'token_pago'     => $p['token'],
					'creado_en'      => $now,
				),
				array( '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s' )
			);
			$ids[] = (int) $wpdb->insert_id;
		}

		return array(
			'plazo1_id' => $ids[0],
			'plazo2_id' => $ids[1],
		);
	}

	/**
	 * Marca plazo com pagat i crea ordre WooCommerce si cal.
	 */
	public static function marcar_pagado(
		int $plazo_id,
		string $pasarela,
		string $referencia_externa = ''
	): void {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_pagos_plazos';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$plazo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE id = %d", $plazo_id ) );
		if ( ! $plazo || 'pagado' === $plazo->estado ) {
			return;
		}

		$order_id = 0;
		if ( class_exists( 'WooCommerce' ) ) {
			$order_id = self::crear_orden_woocommerce( $plazo, $pasarela, $referencia_externa );
		}

		$data   = array(
			'estado'       => 'pagado',
			'fecha_pagado' => current_time( 'mysql' ),
		);
		$format = array( '%s', '%s' );
		if ( $order_id > 0 ) {
			$data['wc_order_id'] = $order_id;
			$format[]            = '%d';
		}

		$wpdb->update(
			$tabla,
			$data,
			array( 'id' => $plazo_id ),
			$format,
			array( '%d' )
		);

		ED_Emails::enviar_confirmacion_pago( (int) $plazo->nucleo_id, $plazo_id, $order_id );
		do_action( 'ed_plazo_pagado', $plazo_id, (int) $plazo->jugador_id, (int) $plazo->nucleo_id );
	}

	/**
	 * Marca fallit; només envia correu si abans estava pendent.
	 */
	public static function marcar_fallido( int $plazo_id, string $motivo = '' ): void {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_pagos_plazos';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$plazo = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$tabla} WHERE id = %d", $plazo_id ) );
		if ( ! $plazo ) {
			return;
		}
		$was_pending = ( 'pendiente' === $plazo->estado );

		$wpdb->update(
			$tabla,
			array( 'estado' => 'fallido' ),
			array( 'id' => $plazo_id ),
			array( '%s' ),
			array( '%d' )
		);

		if ( $was_pending ) {
			ED_Emails::enviar_pago_fallido( (int) $plazo->nucleo_id, $plazo_id );
		}
		do_action( 'ed_plazo_fallido', $plazo_id, (int) $plazo->nucleo_id );
	}

	/**
	 * Plazos d’un nucli amb nom de l’esport.
	 *
	 * @return object[]
	 */
	public static function get_plazos_nucleo( int $nucleo_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT pp.*, p.post_title AS deporte_nombre
				FROM {$wpdb->prefix}ed_pagos_plazos pp
				LEFT JOIN {$wpdb->posts} p ON p.ID = pp.deporte_id
				WHERE pp.nucleo_id = %d
				ORDER BY pp.fecha_prevista ASC",
				$nucleo_id
			)
		);
	}

	/**
	 * Troba plazo 1 pendent per tripleta (webhook Stripe idempotent).
	 */
	public static function find_pending_plazo1( int $jugador_id, int $deporte_id, int $nucleo_id ): ?int {
		global $wpdb;
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}ed_pagos_plazos
				WHERE jugador_id = %d AND deporte_id = %d AND nucleo_id = %d
				AND plazo = 1 AND estado = 'pendiente'
				ORDER BY id DESC LIMIT 1",
				$jugador_id,
				$deporte_id,
				$nucleo_id
			)
		);
		return $id ? (int) $id : null;
	}

	/**
	 * Crea ordre WooCommerce (HPOS-compatible via API d’ordres).
	 *
	 * @param object $plazo Fila de ed_pagos_plazos.
	 */
	private static function crear_orden_woocommerce( object $plazo, string $pasarela, string $referencia ): int {
		if ( ! function_exists( 'wc_create_order' ) ) {
			return 0;
		}

		$user_id = ED_Nucleo_Repository::get_first_adult_user_id( (int) $plazo->nucleo_id );

		$order = wc_create_order(
			array(
				'customer_id' => $user_id,
				'status'      => 'completed',
			)
		);
		if ( is_wp_error( $order ) ) {
			return 0;
		}

		$jugador = get_post( (int) $plazo->jugador_id );
		$deporte = get_post( (int) $plazo->deporte_id );
		$nucleo  = get_post( (int) $plazo->nucleo_id );

		$order->set_payment_method( $pasarela );
		$order->set_payment_method_title( 'stripe' === $pasarela ? 'Stripe' : ( 'redsys' === $pasarela ? 'Redsys' : $pasarela ) );

		$nombre_jugador = '';
		if ( function_exists( 'get_field' ) && $jugador ) {
			$nombre_jugador = trim( (string) get_field( 'ed_jugador_nombre', $jugador->ID ) . ' ' . (string) get_field( 'ed_jugador_apellidos', $jugador->ID ) );
		}
		if ( '' === $nombre_jugador && $jugador ) {
			$nombre_jugador = $jugador->post_title;
		}

		$product_name = sprintf(
			/* translators: 1: sport, 2: player, 3: installment number */
			__( 'Quota %1$s — %2$s (Plaç %3$d)', 'escuela-deportiva-core' ),
			$deporte ? $deporte->post_title : '—',
			$nombre_jugador ?: '—',
			(int) $plazo->plazo
		);

		$item = new WC_Order_Item_Fee();
		$item->set_name( $product_name );
		$item->set_total( (float) $plazo->importe );
		$order->add_item( $item );
		$order->set_total( (float) $plazo->importe );
		$order->add_order_note(
			sprintf(
				'DeportPress: pasarela %s | ref %s | plazo %d | nucleo %d',
				$pasarela,
				$referencia,
				(int) $plazo->plazo,
				(int) $plazo->nucleo_id
			)
		);
		if ( $referencia ) {
			$order->payment_complete( $referencia );
		} else {
			$order->payment_complete();
		}
		$order->save();

		return $order->get_id();
	}
}
