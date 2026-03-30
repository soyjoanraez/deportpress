<?php
/**
 * Scraper del portal FFCV / Novanet (resultadosffcv.isquad.es).
 *
 * Selectors XPath aproximats: cal ajustar-los si canvia el HTML del portal.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Peticions HTTP amb cache i parsing DOM natiu (sense Composer).
 */
class ED_Scraper_FFCV {

	private const BASE_URL   = 'https://resultadosffcv.isquad.es';
	private const CACHE_TIME = 30 * MINUTE_IN_SECONDS;
	private const USER_AGENTS = array(
		'DeportPress/2.5 (Escoles Esportives Ondara; +https://escolesesportivesondara.es)',
		'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
		'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Safari/605.1.15',
		'Mozilla/5.0 (iPhone; CPU iPhone OS 17_3_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.3.1 Mobile/15E148 Safari/604.1',
	);

	/**
	 * @param array<string, string|int> $params Query params.
	 */
	private function fetch( string $url, array $params = array() ): ?string {
		if ( ! empty( $params ) ) {
			$url .= ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $params );
		}

		$cache_key = 'ed_ffcv_' . md5( $url );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return (string) $cached;
		}

		$user_agent = self::USER_AGENTS[ array_rand( self::USER_AGENTS ) ];

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 20,
				'user-agent' => $user_agent,
				'headers'    => array(
					'Accept'           => 'text/html,application/xhtml+xml',
					'Accept-Language'  => 'es-ES,es;q=0.9,ca;q=0.8',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[FFCV Scraper] HTTP: ' . $response->get_error_message() );
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[FFCV Scraper] HTTP ' . (string) $code . ' ' . $url );
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		set_transient( $cache_key, $body, self::CACHE_TIME );

		return $body;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_partidos( string $competicion_id, ?string $grupo_id = null ): array {
		$params = array( 'competicion' => $competicion_id );
		if ( $grupo_id ) {
			$params['grupo'] = $grupo_id;
		}

		$html = $this->fetch( self::BASE_URL . '/competicion/partidos', $params );
		if ( ! $html ) {
			return array();
		}

		return $this->parse_partidos( $html, $competicion_id );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function parse_partidos( string $html, string $competicion_id ): array {
		$partidos = array();

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		$filas = $xpath->query( '//table[contains(@class,"partidos")]//tr[not(th)]' );
		if ( ! $filas || 0 === $filas->length ) {
			$filas = $xpath->query( '//table[contains(@class,"table")]//tbody/tr' );
		}

		if ( ! $filas ) {
			return array();
		}

		foreach ( $filas as $fila ) {
			$celdas = $xpath->query( 'td', $fila );
			if ( ! $celdas || $celdas->length < 5 ) {
				continue;
			}

			$jornada   = trim( $celdas->item( 0 )->textContent ?? '' );
			$fecha_str = trim( $celdas->item( 1 )->textContent ?? '' );
			$local     = trim( $celdas->item( 2 )->textContent ?? '' );
			$resultado = trim( $celdas->item( 3 )->textContent ?? '' );
			$visitante = trim( $celdas->item( 4 )->textContent ?? '' );
			$campo     = $celdas->length > 5 ? trim( $celdas->item( 5 )->textContent ?? '' ) : '';

			$goles_local = null;
			$goles_visit = null;
			if ( preg_match( '/(\d+)\s*-\s*(\d+)/', $resultado, $m ) ) {
				$goles_local = (int) $m[1];
				$goles_visit = (int) $m[2];
			}

			$fecha_hora = null;
			if ( preg_match( '/(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}:\d{2})/', $fecha_str, $fm ) ) {
				$fecha_hora = "{$fm[3]}-{$fm[2]}-{$fm[1]} {$fm[4]}:00";
			}

			$ffcv_id = md5( $competicion_id . '|' . $local . '|' . $visitante . '|' . ( $fecha_hora ?? $jornada ) );

			$partidos[] = array(
				'id'               => $ffcv_id,
				'competicion'      => $competicion_id,
				'jornada'          => is_numeric( $jornada ) ? (int) $jornada : null,
				'equipo_local'     => $local,
				'equipo_visitante' => $visitante,
				'fecha_hora'       => $fecha_hora,
				'campo'            => $campo ? $campo : null,
				'goles_local'      => $goles_local,
				'goles_visitante'  => $goles_visit,
				'estado'           => ( null !== $goles_local ) ? 'jugado' : 'programado',
			);
		}

		return $partidos;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function get_clasificacion( string $competicion_id, ?string $grupo_id = null ): array {
		$params = array( 'competicion' => $competicion_id );
		if ( $grupo_id ) {
			$params['grupo'] = $grupo_id;
		}

		$html = $this->fetch( self::BASE_URL . '/competicion/clasificacion', $params );
		if ( ! $html ) {
			return array();
		}

		return $this->parse_clasificacion( $html, $competicion_id );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function parse_clasificacion( string $html, string $competicion_id ): array {
		$tabla_clasi = array();

		$dom = new DOMDocument();
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html );
		libxml_clear_errors();

		$xpath = new DOMXPath( $dom );

		$filas = $xpath->query( '//table[contains(@class,"clasificacion")]//tr[not(th)]' );
		if ( ! $filas || 0 === $filas->length ) {
			$filas = $xpath->query( '//table[contains(@class,"table")]//tbody/tr' );
		}

		if ( ! $filas ) {
			return array();
		}

		$pos = 1;
		foreach ( $filas as $fila ) {
			$celdas = $xpath->query( 'td', $fila );
			if ( ! $celdas || $celdas->length < 8 ) {
				continue;
			}

			$tabla_clasi[] = array(
				'competicion' => $competicion_id,
				'posicion'    => $pos++,
				'equipo'      => trim( $celdas->item( 0 )->textContent ?? '' ),
				'pj'          => (int) trim( $celdas->item( 1 )->textContent ?? '0' ),
				'pg'          => (int) trim( $celdas->item( 2 )->textContent ?? '0' ),
				'pe'          => (int) trim( $celdas->item( 3 )->textContent ?? '0' ),
				'pp'          => (int) trim( $celdas->item( 4 )->textContent ?? '0' ),
				'gf'          => (int) trim( $celdas->item( 5 )->textContent ?? '0' ),
				'gc'          => (int) trim( $celdas->item( 6 )->textContent ?? '0' ),
				'puntos'      => (int) trim( $celdas->item( 7 )->textContent ?? '0' ),
			);
		}

		return $tabla_clasi;
	}

	public static function limpiar_cache(): void {
		global $wpdb;
		$like = $wpdb->esc_like( '_transient_ed_ffcv_' ) . '%';
		$like_timeout = $wpdb->esc_like( '_transient_timeout_ed_ffcv_' ) . '%';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$like,
				$like_timeout
			)
		);
	}
}
