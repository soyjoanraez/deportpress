<?php
/**
 * Integració Stripe (PaymentIntent, off-session, webhook).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Stripe SDK opcional (composer require stripe/stripe-php).
 */
class ED_Pagos_Stripe {

	/**
	 * @throws RuntimeException
	 */
	private static function client(): \Stripe\StripeClient {
		if ( ! class_exists( \Stripe\StripeClient::class ) ) {
			throw new RuntimeException( 'Stripe SDK no instal·lat. Executa composer install al plugin.' );
		}
		if ( ! defined( 'ED_STRIPE_SECRET_KEY' ) || ! ED_STRIPE_SECRET_KEY ) {
			throw new RuntimeException( 'ED_STRIPE_SECRET_KEY no definida.' );
		}
		return new \Stripe\StripeClient( ED_STRIPE_SECRET_KEY );
	}

	/**
	 * @param array<string,string> $metadata
	 * @return array{client_secret:string,payment_intent_id:string}
	 */
	public static function crear_payment_intent( float $importe, string $currency = 'eur', array $metadata = array(), ?string $customer_id = null ): array {
		$stripe = self::client();
		$params = array(
			'amount'                    => (int) round( $importe * 100 ),
			'currency'                  => $currency,
			'automatic_payment_methods' => array( 'enabled' => true ),
			'setup_future_usage'        => 'off_session',
			'metadata'                  => array_merge( array( 'plataforma' => 'deportpress' ), $metadata ),
		);
		if ( $customer_id ) {
			$params['customer'] = $customer_id;
		}
		$intent = $stripe->paymentIntents->create( $params );
		return array(
			'client_secret'     => (string) $intent->client_secret,
			'payment_intent_id' => (string) $intent->id,
		);
	}

	/**
	 * @return array{ok:bool,status?:string,importe?:float,payment_method?:string,customer_id?:string}
	 */
	public static function confirmar_pago( string $payment_intent_id ): array {
		$stripe = self::client();
		$intent = $stripe->paymentIntents->retrieve( $payment_intent_id );
		if ( 'succeeded' !== $intent->status ) {
			return array(
				'ok'     => false,
				'status' => (string) $intent->status,
			);
		}
		return array(
			'ok'              => true,
			'importe'         => ( (float) $intent->amount ) / 100,
			'payment_method'  => is_string( $intent->payment_method ) ? $intent->payment_method : (string) ( $intent->payment_method->id ?? '' ),
			'customer_id'     => is_string( $intent->customer ) ? $intent->customer : (string) ( $intent->customer->id ?? '' ),
		);
	}

	/**
	 * @return array{ok:bool,payment_intent_id?:string,status?:string,error?:string,code?:string}
	 */
	public static function cobrar_off_session( float $importe, string $payment_method_id, string $customer_id ): array {
		$stripe = self::client();
		try {
			$intent = $stripe->paymentIntents->create(
				array(
					'amount'         => (int) round( $importe * 100 ),
					'currency'       => 'eur',
					'customer'       => $customer_id,
					'payment_method' => $payment_method_id,
					'off_session'    => true,
					'confirm'        => true,
				)
			);
			if ( 'succeeded' === $intent->status ) {
				return array(
					'ok'                => true,
					'payment_intent_id' => (string) $intent->id,
				);
			}
			return array(
				'ok'     => false,
				'status' => (string) $intent->status,
			);
		} catch ( \Stripe\Exception\CardException $e ) {
			return array(
				'ok'    => false,
				'error' => $e->getMessage(),
				'code'  => (string) $e->getStripeCode(),
			);
		} catch ( \Stripe\Exception\ApiErrorException $e ) {
			return array(
				'ok'    => false,
				'error' => $e->getMessage(),
			);
		}
	}

	public static function handle_webhook(): void {
		if ( ! class_exists( \Stripe\Webhook::class ) ) {
			status_header( 500 );
			exit;
		}
		$payload = file_get_contents( 'php://input' );
		$sig     = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';
		$secret  = defined( 'ED_STRIPE_WEBHOOK_SECRET' ) ? ED_STRIPE_WEBHOOK_SECRET : '';

		if ( ! $secret || ! $payload ) {
			status_header( 400 );
			exit;
		}

		try {
			$event = \Stripe\Webhook::constructEvent( $payload, $sig, $secret );
		} catch ( \Exception $e ) {
			status_header( 400 );
			exit;
		}

		switch ( $event->type ) {
			case 'payment_intent.succeeded':
				self::on_payment_succeeded( $event->data->object );
				break;
			case 'payment_intent.payment_failed':
				self::on_payment_failed( $event->data->object );
				break;
		}

		status_header( 200 );
		echo '{"received":true}';
		exit;
	}

	/**
	 * @param object|Stripe\PaymentIntent $intent
	 */
	private static function on_payment_succeeded( $intent ): void {
		$meta = self::intent_metadata_array( $intent );
		$jid  = isset( $meta['ed_jugador_id'] ) ? (int) $meta['ed_jugador_id'] : 0;
		$did  = isset( $meta['ed_deporte_id'] ) ? (int) $meta['ed_deporte_id'] : 0;
		$nid  = isset( $meta['ed_nucleo_id'] ) ? (int) $meta['ed_nucleo_id'] : 0;
		$piid = (string) $intent->id;

		$plazo_id = ( $jid && $did && $nid ) ? ED_Pagos::find_pending_plazo1( $jid, $did, $nid ) : null;
		if ( $plazo_id ) {
			ED_Pagos::marcar_pagado( $plazo_id, 'stripe', $piid );
			return;
		}

		global $wpdb;
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ed_pagos_plazos WHERE jugador_id = %d AND deporte_id = %d AND nucleo_id = %d",
				$jid,
				$did,
				$nid
			)
		);
		if ( $exists > 0 || ! $jid || ! $did || ! $nid ) {
			return;
		}

		$pm = is_string( $intent->payment_method ) ? $intent->payment_method : (string) ( $intent->payment_method->id ?? '' );
		$cus = is_string( $intent->customer ) ? $intent->customer : (string) ( $intent->customer->id ?? '' );
		$token = $pm && $cus ? $pm . '|' . $cus : null;

		$res = ED_Pagos::crear_plazos( $nid, $jid, $did, 'stripe', $token );
		if ( ! empty( $res['plazo1_id'] ) ) {
			ED_Pagos::marcar_pagado( (int) $res['plazo1_id'], 'stripe', $piid );
		}
	}

	/**
	 * @param object|Stripe\PaymentIntent $intent
	 */
	private static function on_payment_failed( $intent ): void {
		$meta = self::intent_metadata_array( $intent );
		$jid  = isset( $meta['ed_jugador_id'] ) ? (int) $meta['ed_jugador_id'] : 0;
		$did  = isset( $meta['ed_deporte_id'] ) ? (int) $meta['ed_deporte_id'] : 0;
		$nid  = isset( $meta['ed_nucleo_id'] ) ? (int) $meta['ed_nucleo_id'] : 0;
		if ( ! $jid || ! $did || ! $nid ) {
			return;
		}
		$plazo_id = ED_Pagos::find_pending_plazo1( $jid, $did, $nid );
		if ( $plazo_id ) {
			$msg = '';
			if ( isset( $intent->last_payment_error ) && is_object( $intent->last_payment_error ) ) {
				$msg = (string) ( $intent->last_payment_error->message ?? '' );
			}
			ED_Pagos::marcar_fallido( $plazo_id, $msg );
		}
	}

	/**
	 * @param object $intent PaymentIntent
	 * @return array<string,string>
	 */
	private static function intent_metadata_array( $intent ): array {
		$m = $intent->metadata ?? null;
		if ( null === $m ) {
			return array();
		}
		if ( is_array( $m ) ) {
			return array_map( 'strval', $m );
		}
		if ( is_object( $m ) && method_exists( $m, 'toArray' ) ) {
			return array_map( 'strval', $m->toArray() );
		}
		if ( is_iterable( $m ) ) {
			$out = array();
			foreach ( $m as $k => $v ) {
				$out[ (string) $k ] = (string) $v;
			}
			return $out;
		}
		return array();
	}
}
