<?php
/**
 * Integració Redsys (firma HMAC SHA-256, IPN).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cal validar amb el kit oficial Redsys abans de producció.
 */
class ED_Pagos_Redsys {

	private const VERSION = 'HMAC_SHA256_V1';

	private static function url_entorno(): string {
		if ( defined( 'WP_ENVIRONMENT_TYPE' ) && 'production' === WP_ENVIRONMENT_TYPE ) {
			return 'https://sis.redsys.es/sis/realizarPago';
		}
		return 'https://sis-t.redsys.es:25443/sis/realizarPago';
	}

	/**
	 * @throws RuntimeException
	 */
	private static function clave_binaria(): string {
		if ( ! defined( 'ED_REDSYS_SECRET_KEY' ) || ! ED_REDSYS_SECRET_KEY ) {
			throw new RuntimeException( 'ED_REDSYS_SECRET_KEY no definida.' );
		}
		$key = base64_decode( ED_REDSYS_SECRET_KEY, true );
		if ( false === $key ) {
			throw new RuntimeException( 'ED_REDSYS_SECRET_KEY no és base64 vàlid.' );
		}
		return $key;
	}

	/**
	 * Genera formulari HTML per al primer pagament.
	 */
	public static function generar_formulario(
		float $importe,
		int $plazo_id,
		string $url_ok,
		string $url_ko
	): string {
		$merchant_code = defined( 'ED_REDSYS_MERCHANT_CODE' ) ? ED_REDSYS_MERCHANT_CODE : '';
		$terminal      = defined( 'ED_REDSYS_TERMINAL' ) ? ED_REDSYS_TERMINAL : '001';

		$params = array(
			'DS_MERCHANT_AMOUNT'           => (string) (int) round( $importe * 100 ),
			'DS_MERCHANT_ORDER'            => self::generar_order( $plazo_id ),
			'DS_MERCHANT_MERCHANTCODE'     => $merchant_code,
			'DS_MERCHANT_CURRENCY'         => '978',
			'DS_MERCHANT_TRANSACTIONTYPE'  => '0',
			'DS_MERCHANT_TERMINAL'         => $terminal,
			'DS_MERCHANT_MERCHANTURL'      => esc_url_raw( rest_url( 'ed/v1/redsys/notificacion' ) ),
			'DS_MERCHANT_URLNOTIFICATION'  => esc_url_raw( rest_url( 'ed/v1/redsys/notificacion' ) ),
			'DS_MERCHANT_URLOK'            => $url_ok,
			'DS_MERCHANT_URLKO'            => $url_ko,
		);

		$params_b64 = base64_encode( wp_json_encode( $params ) );
		$key        = self::clave_binaria();
		$order      = $params['DS_MERCHANT_ORDER'];
		$key_crypt  = self::encrypt_3des( $params_b64, $order, $key );
		$firma      = base64_encode( hash_hmac( 'sha256', $params_b64, $key_crypt, true ) );

		return sprintf(
			'<form method="post" action="%1$s" id="ed-redsys-form" class="ed-redsys-form">
				<input type="hidden" name="Ds_SignatureVersion" value="%2$s" />
				<input type="hidden" name="Ds_MerchantParameters" value="%3$s" />
				<input type="hidden" name="Ds_Signature" value="%4$s" />
				<button type="submit" class="ed-panel-familiar__btn ed-panel-familiar__btn--primary">%5$s</button>
			</form>',
			esc_url( self::url_entorno() ),
			esc_attr( self::VERSION ),
			esc_attr( $params_b64 ),
			esc_attr( $firma ),
			esc_html__( 'Pagar amb targeta', 'escuela-deportiva-core' )
		);
	}

	public static function procesar_notificacion( WP_REST_Request $request ): WP_REST_Response {
		$params_b64 = $request->get_param( 'Ds_MerchantParameters' );
		if ( ! is_string( $params_b64 ) ) {
			$params_b64 = '';
		}
		$firma_recv = $request->get_param( 'Ds_Signature' );
		if ( ! is_string( $firma_recv ) ) {
			$firma_recv = '';
		}
		$version = $request->get_param( 'Ds_SignatureVersion' );
		if ( ! is_string( $version ) ) {
			$version = '';
		}

		if ( self::VERSION !== $version || '' === $params_b64 || '' === $firma_recv ) {
			return new WP_REST_Response( array( 'error' => 'invalid_params' ), 400 );
		}

		try {
			$key       = self::clave_binaria();
			$decoded   = json_decode( base64_decode( $params_b64, true ) ?: '', true );
			if ( ! is_array( $decoded ) ) {
				return new WP_REST_Response( array( 'error' => 'bad_payload' ), 400 );
			}
			$order     = isset( $decoded['Ds_Order'] ) ? (string) $decoded['Ds_Order'] : '';
			$key_crypt = self::encrypt_3des( $params_b64, $order, $key );
			$firma_calc = base64_encode( hash_hmac( 'sha256', $params_b64, $key_crypt, true ) );
			if ( ! hash_equals( $firma_calc, $firma_recv ) ) {
				return new WP_REST_Response( array( 'error' => 'bad_signature' ), 401 );
			}
			$respuesta = isset( $decoded['Ds_Response'] ) ? (int) $decoded['Ds_Response'] : 9999;
			$plazo_id  = self::plazo_id_desde_order( $order );
			if ( $respuesta >= 0 && $respuesta <= 99 ) {
				ED_Pagos::marcar_pagado( $plazo_id, 'redsys', $order );
			} else {
				ED_Pagos::marcar_fallido( $plazo_id, 'Redsys: ' . $respuesta );
			}
		} catch ( RuntimeException $e ) {
			return new WP_REST_Response( array( 'error' => 'config' ), 500 );
		}

		return new WP_REST_Response( array( 'resultado' => 'OK' ), 200 );
	}

	public static function generar_order( int $plazo_id ): string {
		return str_pad( (string) $plazo_id, 12, '0', STR_PAD_LEFT );
	}

	public static function plazo_id_desde_order( string $order ): int {
		return (int) ltrim( $order, '0' );
	}

	private static function encrypt_3des( string $data, string $order, string $key ): string {
		$key = substr( $key, 0, 24 );
		$iv  = str_pad( substr( $order, 0, 8 ), 8, "\0" );
		$enc = openssl_encrypt( $data, 'des-ede3-cbc', $key, OPENSSL_RAW_DATA, $iv );
		return false !== $enc ? $enc : '';
	}
}
