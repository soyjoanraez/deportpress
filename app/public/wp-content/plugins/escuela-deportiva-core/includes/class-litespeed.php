<?php
/**
 * Exclusions de cache LiteSpeed per a rutes dinàmiques DeportPress.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Força nocache quan LSCWP està actiu (API oficial).
 */
class ED_LiteSpeed {

	/**
	 * Hooks.
	 */
	public function register(): void {
		add_action( 'template_redirect', array( $this, 'maybe_set_nocache' ), 0 );
	}

	/**
	 * Marca la petició com a no cachejable si la URI coincideix amb les rutes dinàmiques.
	 */
	public function maybe_set_nocache(): void {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return;
		}

		$uri = wp_unslash( $_SERVER['REQUEST_URI'] );

		$paths = array(
			'/panel-familiar/',
			'/enviar-mensaje/',
			'/mi-panel/',
			'/panel-entrenador/',
			'/escaner-qr/',
			'/panel-bar/',
			'/validar-entrada/',
			'/votar-mvp/',
			'/manifest.json',
			'/wp-json/ed/v1/stripe/webhook',
			'/wp-json/ed/v1/redsys/notificacion',
			'/wp-json/ed/v1/partido/',
			'/wp-json/ed/v1/torneo/',
			'/wp-json/ed/v1/rankings',
			'/wp-json/ed/v1/push/',
			'/wp-json/ed/v1/directorio',
			'/wp-json/ed/v1/publicidad/',
			'/directorio/',
			'/wc-api/',
			'/wp-json/',
			'/carrito/',
			'/cart/',
			'/mi-cuenta/',
			'/my-account/',
			'/OneSignalSDKWorker.js',
			'/OneSignalSDK.sw.js',
			'/OneSignalSDKUpdaterWorker.js',
		);

		foreach ( $paths as $p ) {
			if ( false !== strpos( $uri, $p ) ) {
				$this->trigger_nocache();
				return;
			}
		}

		if ( preg_match( '#/torneo/[^/]+/partido/#', $uri ) ) {
			$this->trigger_nocache();
			return;
		}

		if ( false !== strpos( $uri, '/resultados' ) || preg_match( '#/equipo/[^/]+#', $uri ) ) {
			$this->trigger_nocache();
		}
	}

	/**
	 * Crida l’acció de LiteSpeed si existeix.
	 */
	private function trigger_nocache(): void {
		if ( has_action( 'litespeed_control_set_nocache' ) ) {
			do_action( 'litespeed_control_set_nocache', 'ed_dynamic_route' );
		}
	}
}
