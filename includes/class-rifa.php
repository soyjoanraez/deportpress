<?php
/**
 * Rifa digital (Fase 6).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Números de rifa i sorteig.
 */
class ED_Rifa {

	/**
	 * @return array{activa:bool,numeros_por_entrada:int,rango_inicio:int,rango_fin:int}
	 */
	public static function get_config( int $torneo_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array(
				'activa'              => false,
				'numeros_por_entrada' => 1,
				'rango_inicio'        => 0,
				'rango_fin'           => 1000,
			);
		}
		return array(
			'activa'              => (bool) get_field( ED_Torneos::TOR_RIFA_ACTIVA, $torneo_id ),
			'numeros_por_entrada' => max( 1, (int) ( get_field( ED_Torneos::TOR_RIFA_NUMS_ENTRADA, $torneo_id ) ?: 1 ) ),
			'rango_inicio'        => (int) ( get_field( ED_Torneos::TOR_RIFA_RANGO_INICIO, $torneo_id ) ?: 0 ),
			'rango_fin'           => (int) ( get_field( ED_Torneos::TOR_RIFA_RANGO_FIN, $torneo_id ) ?: 1000 ),
		);
	}

	/**
	 * @return int[]
	 */
	public static function asignar_numeros( int $torneo_id, int $entrada_id ): array {
		$config = self::get_config( $torneo_id );
		if ( ! $config['activa'] ) {
			return array();
		}

		global $wpdb;
		$tabla     = $wpdb->prefix . 'ed_rifa_numeros';
		$asignados = array();

		for ( $i = 0; $i < $config['numeros_por_entrada']; $i++ ) {
			$numero = self::get_siguiente_numero( $torneo_id, $config );
			if ( null === $numero ) {
				break;
			}
			$wpdb->insert(
				$tabla,
				array(
					'torneo_id'  => $torneo_id,
					'entrada_id' => $entrada_id,
					'numero'     => $numero,
				),
				array( '%d', '%d', '%d' )
			);
			$asignados[] = $numero;
		}

		return $asignados;
	}

	/**
	 * @param array{rango_inicio:int,rango_fin:int} $config
	 */
	private static function get_siguiente_numero( int $torneo_id, array $config ): ?int {
		global $wpdb;
		$tabla = $wpdb->prefix . 'ed_rifa_numeros';

		$usados = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT numero FROM {$tabla} WHERE torneo_id = %d",
				$torneo_id
			)
		);
		$usados = array_map( 'intval', $usados );

		$pool = array_diff(
			range( $config['rango_inicio'], $config['rango_fin'] ),
			$usados
		);

		if ( empty( $pool ) ) {
			return null;
		}

		return min( $pool );
	}

	/**
	 * @return int[]
	 */
	public static function get_numeros_entrada( int $entrada_id ): array {
		global $wpdb;
		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT numero FROM {$wpdb->prefix}ed_rifa_numeros
					WHERE entrada_id = %d ORDER BY numero ASC",
					$entrada_id
				)
			)
		);
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_numeros_torneo( int $torneo_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rn.numero, rn.premiado, rn.premio,
					e.nombre_titular, e.email_titular, e.id AS entrada_id
				FROM {$wpdb->prefix}ed_rifa_numeros rn
				JOIN {$wpdb->prefix}ed_entradas e ON e.id = rn.entrada_id
				WHERE rn.torneo_id = %d
				ORDER BY rn.numero ASC",
				$torneo_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_ganadores( int $torneo_id ): array {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT rn.numero, rn.premio,
					e.nombre_titular, e.email_titular
				FROM {$wpdb->prefix}ed_rifa_numeros rn
				JOIN {$wpdb->prefix}ed_entradas e ON e.id = rn.entrada_id
				WHERE rn.torneo_id = %d AND rn.premiado = 1
				ORDER BY rn.numero ASC",
				$torneo_id
			)
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @param array<int, array{numero:int, descripcion?:string}> $premios
	 * @return array<int, array<string, mixed>>
	 */
	public static function realizar_sorteo( int $torneo_id, array $premios ): array {
		global $wpdb;
		$tabla     = $wpdb->prefix . 'ed_rifa_numeros';
		$resultado = array();

		foreach ( $premios as $premio ) {
			$numero = (int) ( $premio['numero'] ?? 0 );
			$desc   = sanitize_text_field( (string) ( $premio['descripcion'] ?? '' ) );

			$fila = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT rn.*, e.nombre_titular, e.email_titular
					FROM {$tabla} rn
					JOIN {$wpdb->prefix}ed_entradas e ON e.id = rn.entrada_id
					WHERE rn.torneo_id = %d AND rn.numero = %d",
					$torneo_id,
					$numero
				)
			);

			if ( ! $fila ) {
				$resultado[] = array(
					'numero' => $numero,
					'ok'     => false,
					'error'  => __( 'Número no venut', 'escuela-deportiva-core' ),
				);
				continue;
			}

			$wpdb->update(
				$tabla,
				array(
					'premiado' => 1,
					'premio'   => $desc,
				),
				array(
					'torneo_id' => $torneo_id,
					'numero'    => $numero,
				),
				array( '%d', '%s' ),
				array( '%d', '%d' )
			);

			self::enviar_email_ganador(
				(string) $fila->email_titular,
				(string) $fila->nombre_titular,
				$numero,
				$desc,
				(string) get_post_field( 'post_title', $torneo_id )
			);

			$resultado[] = array(
				'numero'  => $numero,
				'ok'      => true,
				'titular' => $fila->nombre_titular,
				'premio'  => $desc,
			);
		}

		update_post_meta( $torneo_id, '_ed_rifa_ganadores', wp_json_encode( $resultado ) );
		do_action( 'ed_rifa_sorteo_realizado', $torneo_id, $resultado );

		return $resultado;
	}

	/**
	 * @param string[] $premios_desc
	 * @return array<int, array<string, mixed>>
	 */
	public static function sorteo_aleatorio( int $torneo_id, array $premios_desc ): array {
		global $wpdb;

		$numeros_vendidos = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT numero FROM {$wpdb->prefix}ed_rifa_numeros WHERE torneo_id = %d",
				$torneo_id
			)
		);

		if ( empty( $numeros_vendidos ) ) {
			return array();
		}

		shuffle( $numeros_vendidos );
		$ganadores = array_slice( $numeros_vendidos, 0, count( $premios_desc ) );

		$premios = array();
		foreach ( $premios_desc as $i => $desc ) {
			$premios[] = array(
				'numero'      => isset( $ganadores[ $i ] ) ? (int) $ganadores[ $i ] : null,
				'descripcion' => sanitize_text_field( (string) $desc ),
			);
		}
		$premios = array_values(
			array_filter(
				$premios,
				static function ( $p ) {
					return null !== $p['numero'];
				}
			)
		);

		return self::realizar_sorteo( $torneo_id, $premios );
	}

	private static function enviar_email_ganador(
		string $email,
		string $nombre,
		int $numero,
		string $premio,
		string $torneo
	): void {
		if ( ! is_email( $email ) ) {
			return;
		}

		add_filter( 'wp_mail_content_type', array( __CLASS__, 'filter_mail_html' ) );
		wp_mail(
			$email,
			sprintf(
				/* translators: %s: tournament name */
				__( 'Has guanyat a la rifa de %s', 'escuela-deportiva-core' ),
				$torneo
			),
			sprintf(
				'<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">
				<div style="background:#1A1A1A;padding:24px 32px;text-align:center;">
				<span style="color:#FFD600;font-size:28px;font-weight:700;">ESCOLES ESPORTIVES ONDARA</span>
				</div>
				<div style="padding:32px;text-align:center;">
				<h2 style="color:#1A1A1A;">%s</h2>
				<p>%s <strong style="font-size:24px;color:#1A1A1A;">%04d</strong>
				%s <strong>%s</strong>.</p>
				<div style="background:#FFF9C4;border-radius:8px;padding:16px;margin:24px 0;"><strong>%s</strong></div>
				<p style="color:#757575;font-size:13px;">%s</p>
				</div></div>',
				sprintf(
					/* translators: %s: winner name */
					esc_html__( 'Enhorabona, %s!', 'escuela-deportiva-core' ),
					esc_html( $nombre )
				),
				esc_html__( 'El teu número', 'escuela-deportiva-core' ),
				$numero,
				esc_html__( 'ha estat premiat a la rifa de', 'escuela-deportiva-core' ),
				esc_html( $torneo ),
				esc_html( $premio ),
				esc_html__( 'Presenta’t a la taula de premis amb la teva entrada per recollir-lo.', 'escuela-deportiva-core' )
			)
		);
		remove_filter( 'wp_mail_content_type', array( __CLASS__, 'filter_mail_html' ) );
	}

	public static function filter_mail_html(): string {
		return 'text/html';
	}
}
