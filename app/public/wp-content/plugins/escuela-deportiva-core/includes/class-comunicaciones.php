<?php
/**
 * Missatgeria interna club / staff → famílies (Fase 9).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registre de missatges, recepcions per nucli, correu i push.
 */
class ED_Comunicaciones {

	public const TIPO_CLUB      = 'club';
	public const TIPO_DEPORTE   = 'deporte';
	public const TIPO_CATEGORIA = 'categoria';
	public const TIPO_NUCLEO    = 'nucleo';
	public const TIPO_JUGADOR   = 'jugador';

	/**
	 * @return string[]
	 */
	public static function tipos_destino_validos(): array {
		return array(
			self::TIPO_CLUB,
			self::TIPO_DEPORTE,
			self::TIPO_CATEGORIA,
			self::TIPO_NUCLEO,
			self::TIPO_JUGADOR,
		);
	}

	/**
	 * @param int         $remitente_id User ID.
	 * @param string      $tipo_destino Un de {@see tipos_destino_validos()}.
	 * @param int|null    $destino_id   ID esport / categoria / nucli / jugador segons tipus.
	 * @param string      $asunto       Títol.
	 * @param string      $cuerpo       Cos HTML segur (wp_kses_post).
	 * @return int|false ID del missatge o false.
	 */
	public static function enviar( int $remitente_id, string $tipo_destino, ?int $destino_id, string $asunto, string $cuerpo ) {
		global $wpdb;

		if ( ! self::usuario_puede_enviar( $remitente_id, $tipo_destino, $destino_id ) ) {
			return false;
		}

		$tipo_destino = sanitize_key( $tipo_destino );
		if ( ! in_array( $tipo_destino, self::tipos_destino_validos(), true ) ) {
			return false;
		}

		$nucleos = self::get_nucleos_destinatarios( $tipo_destino, $destino_id );
		$nucleos = array_values( array_unique( array_map( 'intval', $nucleos ) ) );
		if ( array() === $nucleos ) {
			return false;
		}

		$tabla_m = $wpdb->prefix . 'ed_mensajes';
		$ok      = $wpdb->insert(
			$tabla_m,
			array(
				'remitente_id' => $remitente_id,
				'tipo_destino' => $tipo_destino,
				'destino_id'   => $destino_id,
				'asunto'       => sanitize_text_field( $asunto ),
				'cuerpo'       => wp_kses_post( $cuerpo ),
			),
			array( '%d', '%s', '%d', '%s', '%s' )
		);

		if ( ! $ok ) {
			return false;
		}

		$mensaje_id = (int) $wpdb->insert_id;
		if ( $mensaje_id <= 0 ) {
			return false;
		}

		$tabla_r = $wpdb->prefix . 'ed_msg_recepciones';
		foreach ( $nucleos as $nucleo_id ) {
			if ( $nucleo_id <= 0 ) {
				continue;
			}
			$wpdb->insert(
				$tabla_r,
				array(
					'mensaje_id' => $mensaje_id,
					'nucleo_id'  => $nucleo_id,
					'email_enviado' => 0,
				),
				array( '%d', '%d', '%d' )
			);
		}

		// Encolamos el trabajo de envíos de correo asíncronos para evitar timeout del server
		wp_schedule_single_event( time(), 'ed_cron_procesar_cola_emails' );

		$push_ok = self::enviar_push_mensaje( $tipo_destino, $destino_id, $asunto, $mensaje_id );

		$wpdb->update(
			$tabla_m,
			array(
				'enviado_email' => 1,
				'enviado_push'  => $push_ok ? 1 : 0,
			),
			array( 'id' => $mensaje_id ),
			array( '%d', '%d' ),
			array( '%d' )
		);

		do_action( 'ed_mensaje_enviado', $mensaje_id, $nucleos );

		return $mensaje_id;
	}

	/**
	 * Admin / coordinador: tot. Entrenador: categories on entrena (sessions o llista `ed_dep_entrenadores` de l’esport).
	 *
	 * @param int|null $destino_id Context per tipus.
	 */
	public static function usuario_puede_enviar( int $user_id, string $tipo_destino, ?int $destino_id ): bool {
		if ( $user_id <= 0 ) {
			return false;
		}
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		if ( user_can( $user, 'manage_options' ) || user_can( $user, 'manage_escuela_deportiva' ) ) {
			return true;
		}

		if ( ! user_can( $user, 'edit_entrenamientos' ) ) {
			return false;
		}

		$tipo_destino = sanitize_key( $tipo_destino );
		$did          = $destino_id ? (int) $destino_id : 0;

		switch ( $tipo_destino ) {
			case self::TIPO_CLUB:
			case self::TIPO_NUCLEO:
				return false;
			case self::TIPO_CATEGORIA:
				return $did > 0 && in_array( $did, self::get_categoria_ids_entrenador( $user_id ), true );
			case self::TIPO_JUGADOR:
				if ( $did <= 0 || get_post_type( $did ) !== 'jugador' ) {
					return false;
				}
				$cat = function_exists( 'get_field' ) ? (int) get_field( 'ed_jugador_categoria', $did, false ) : 0;
				return $cat > 0 && in_array( $cat, self::get_categoria_ids_entrenador( $user_id ), true );
			case self::TIPO_DEPORTE:
				if ( $did <= 0 ) {
					return false;
				}
				foreach ( self::get_categoria_ids_entrenador( $user_id ) as $cid ) {
					$dep = function_exists( 'get_field' ) ? (int) get_field( 'ed_cat_deporte', $cid, false ) : 0;
					if ( $dep === $did ) {
						return true;
					}
				}
				return false;
			default:
				return false;
		}
	}

	/**
	 * IDs d’esports on l’usuari figura al camp `ed_dep_entrenadores`.
	 *
	 * @return int[]
	 */
	public static function get_deporte_ids_entrenador_asignados( int $user_id ): array {
		if ( $user_id <= 0 || ! function_exists( 'get_field' ) ) {
			return array();
		}
		$posts = get_posts(
			array(
				'post_type'      => 'deporte',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $posts as $did ) {
			$did = (int) $did;
			$coaches = get_field( 'ed_dep_entrenadores', $did, false );
			if ( empty( $coaches ) ) {
				continue;
			}
			if ( ! is_array( $coaches ) ) {
				$coaches = array( $coaches );
			}
			$coaches = array_map( 'intval', $coaches );
			if ( in_array( $user_id, $coaches, true ) ) {
				$out[] = $did;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * True si l’usuari està a `ed_dep_entrenadores` de l’esport de la categoria.
	 */
	public static function user_entrenador_deporte_de_categoria( int $user_id, int $categoria_id ): bool {
		if ( $user_id <= 0 || $categoria_id <= 0 || ! function_exists( 'get_field' ) ) {
			return false;
		}
		if ( 'categoria' !== get_post_type( $categoria_id ) ) {
			return false;
		}
		$dep = (int) get_field( 'ed_cat_deporte', $categoria_id, false );
		if ( $dep <= 0 ) {
			return false;
		}
		$coaches = get_field( 'ed_dep_entrenadores', $dep, false );
		if ( empty( $coaches ) ) {
			return false;
		}
		if ( ! is_array( $coaches ) ) {
			$coaches = array( $coaches );
		}
		$coaches = array_map( 'intval', $coaches );
		return in_array( $user_id, $coaches, true );
	}

	/**
	 * Categories on l’usuari entrena (sessions) o on consta com a entrenador de l’esport (`ed_dep_entrenadores`).
	 *
	 * @return int[]
	 */
	public static function get_categoria_ids_entrenador( int $user_id ): array {
		if ( ! function_exists( 'get_field' ) ) {
			return array();
		}
		$cats = array();
		$posts = get_posts(
			array(
				'post_type'      => 'entrenamiento',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array(
					array(
						'key'   => 'ed_ent_entrenador',
						'value' => $user_id,
					),
				),
			)
		);
		foreach ( $posts as $pid ) {
			$c = (int) get_field( 'ed_ent_categoria', (int) $pid, false );
			if ( $c ) {
				$cats[ $c ] = $c;
			}
		}
		foreach ( self::get_deporte_ids_entrenador_asignados( $user_id ) as $did ) {
			$cat_posts = get_posts(
				array(
					'post_type'      => 'categoria',
					'post_status'    => 'any',
					'posts_per_page' => -1,
					'fields'         => 'ids',
					'no_found_rows'  => true,
					'meta_query'     => array(
						array(
							'key'   => 'ed_cat_deporte',
							'value' => $did,
						),
					),
				)
			);
			foreach ( $cat_posts as $cid ) {
				$cid = (int) $cid;
				$cats[ $cid ] = $cid;
			}
		}
		return array_values( $cats );
	}

	/**
	 * @return int[]
	 */
	private static function get_nucleos_destinatarios( string $tipo, ?int $id ): array {
		switch ( $tipo ) {
			case self::TIPO_CLUB:
				return self::get_todos_los_nucleos();
			case self::TIPO_DEPORTE:
				return $id ? self::get_nucleos_por_deporte( $id ) : array();
			case self::TIPO_CATEGORIA:
				return $id ? self::get_nucleos_por_categoria( $id ) : array();
			case self::TIPO_NUCLEO:
				return ( $id && $id > 0 ) ? array( $id ) : array();
			case self::TIPO_JUGADOR:
				if ( ! $id ) {
					return array();
				}
				$nucleo_id = self::get_nucleo_de_jugador( $id );
				return $nucleo_id ? array( $nucleo_id ) : array();
			default:
				return array();
		}
	}

	/**
	 * @return int[]
	 */
	private static function get_todos_los_nucleos(): array {
		$posts = get_posts(
			array(
				'post_type'      => 'nucleo_familiar',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			)
		);
		return array_map( 'intval', $posts );
	}

	/**
	 * @return int[]
	 */
	private static function get_nucleos_por_deporte( int $deporte_id ): array {
		$jugadores = get_posts(
			array(
				'post_type'      => 'jugador',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => 'ed_jugador_deporte',
						'value' => $deporte_id,
					),
					array(
						'key'   => 'ed_jugador_estado',
						'value' => 'activo',
					),
				),
			)
		);

		$nucleos = array();
		foreach ( $jugadores as $jid ) {
			$nid = self::get_nucleo_de_jugador( (int) $jid );
			if ( $nid ) {
				$nucleos[ $nid ] = $nid;
			}
		}
		return array_values( $nucleos );
	}

	/**
	 * @return int[]
	 */
	private static function get_nucleos_por_categoria( int $categoria_id ): array {
		$jugadores = get_posts(
			array(
				'post_type'      => 'jugador',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
				'meta_query'     => array(
					array(
						'key'   => 'ed_jugador_categoria',
						'value' => $categoria_id,
					),
				),
			)
		);

		$nucleos = array();
		foreach ( $jugadores as $jid ) {
			$nid = self::get_nucleo_de_jugador( (int) $jid );
			if ( $nid ) {
				$nucleos[ $nid ] = $nid;
			}
		}
		return array_values( $nucleos );
	}

	private static function get_nucleo_de_jugador( int $jugador_id ): ?int {
		if ( ! function_exists( 'get_field' ) ) {
			$n = (int) get_post_meta( $jugador_id, 'ed_jugador_nucleo', true );
			return $n > 0 ? $n : null;
		}
		$n = (int) get_field( 'ed_jugador_nucleo', $jugador_id, false );
		return $n > 0 ? $n : null;
	}

	private static function enviar_email_nucleo(
		int $nucleo_id,
		string $asunto,
		string $cuerpo,
		int $remitente_id
	): void {
		if ( ! function_exists( 'get_field' ) ) {
			return;
		}

		$adultos = get_field( 'ed_nucleo_adultos', $nucleo_id, false );
		if ( empty( $adultos ) ) {
			return;
		}
		if ( ! is_array( $adultos ) ) {
			$adultos = array( $adultos );
		}

		$remitente        = get_userdata( $remitente_id );
		$nombre_remitente = $remitente ? $remitente->display_name : __( 'El club', 'escuela-deportiva-core' );
		$blog             = wp_specialchars_decode( get_option( 'blogname', '' ), ENT_QUOTES );
		$panel_url        = home_url( '/panel-familiar/' );

		add_filter( 'wp_mail_content_type', array( self::class, 'mail_content_type_html' ) );

		foreach ( $adultos as $user_raw ) {
			$uid = (int) ( is_object( $user_raw ) && isset( $user_raw->ID ) ? $user_raw->ID : $user_raw );
			if ( $uid <= 0 ) {
				continue;
			}
			$u = get_userdata( $uid );
			if ( ! $u || ! is_email( $u->user_email ) ) {
				continue;
			}

			$body = sprintf(
				'<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;background:#fff;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden;">
					<div style="background:#1A1A1A;padding:24px 32px;text-align:center;">
						<span style="color:#FFD600;font-size:28px;font-weight:700;">%s</span>
					</div>
					<div style="padding:32px;">
						<p style="color:#757575;font-size:13px;margin:0 0 16px;">%s <strong>%s</strong></p>
						<h2 style="color:#1A1A1A;margin:0 0 16px;">%s</h2>
						<div style="line-height:1.7;color:#333;">%s</div>
						<hr style="border:none;border-top:1px solid #eee;margin:24px 0;">
						<p style="font-size:12px;color:#999;"><a href="%s">%s</a></p>
					</div>
				</div>',
				esc_html( $blog ),
				esc_html__( 'Missatge de', 'escuela-deportiva-core' ),
				esc_html( $nombre_remitente ),
				esc_html( sanitize_text_field( $asunto ) ),
				wp_kses_post( wpautop( $cuerpo ) ),
				esc_url( $panel_url ),
				esc_html__( 'Obrir el panel familiar', 'escuela-deportiva-core' )
			);

			wp_mail(
				$u->user_email,
				sprintf( '[%s] %s', $blog, sanitize_text_field( $asunto ) ),
				$body
			);
		}

		remove_filter( 'wp_mail_content_type', array( self::class, 'mail_content_type_html' ) );
	}

	public static function mail_content_type_html(): string {
		return 'text/html';
	}

	private static function enviar_push_mensaje(
		string $tipo,
		?int $destino_id,
		string $asunto,
		int $mensaje_id
	): bool {
		if ( ! class_exists( 'ED_Push' ) ) {
			return false;
		}

		$url = home_url( '/panel-familiar/' );

		switch ( $tipo ) {
			case self::TIPO_CLUB:
				$filtros = array(
					array(
						'field'    => 'tag',
						'key'      => 'es_familia',
						'relation' => '=',
						'value'    => '1',
					),
				);
				break;
			case self::TIPO_DEPORTE:
				if ( ! $destino_id ) {
					return false;
				}
				$filtros = array(
					array(
						'field'    => 'tag',
						'key'      => 'deporte_' . $destino_id,
						'relation' => '=',
						'value'    => '1',
					),
				);
				break;
			case self::TIPO_CATEGORIA:
				if ( ! $destino_id ) {
					return false;
				}
				$filtros = array(
					array(
						'field'    => 'tag',
						'key'      => 'categoria_' . $destino_id,
						'relation' => '=',
						'value'    => '1',
					),
				);
				break;
			case self::TIPO_NUCLEO:
				if ( ! $destino_id ) {
					return false;
				}
				$filtros = array(
					array(
						'field'    => 'tag',
						'key'      => 'nucleo_' . $destino_id,
						'relation' => '=',
						'value'    => '1',
					),
				);
				break;
			case self::TIPO_JUGADOR:
				$nid = $destino_id ? self::get_nucleo_de_jugador( $destino_id ) : null;
				if ( ! $nid ) {
					return false;
				}
				$filtros = array(
					array(
						'field'    => 'tag',
						'key'      => 'nucleo_' . $nid,
						'relation' => '=',
						'value'    => '1',
					),
				);
				break;
			default:
				return false;
		}

		return ED_Push::enviar(
			__( '💬 Nou missatge', 'escuela-deportiva-core' ),
			$asunto,
			$filtros,
			$url,
			array( 'tipo' => 'mensaje_club', 'mensaje_id' => $mensaje_id )
		);
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_mensajes_nucleo( int $nucleo_id, int $limit = 20 ): array {
		global $wpdb;
		$limit = max( 1, min( 100, $limit ) );
		$tabla_m = $wpdb->prefix . 'ed_mensajes';
		$tabla_r = $wpdb->prefix . 'ed_msg_recepciones';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT m.*, r.leido, r.leido_en, u.display_name AS remitente_nombre
			FROM {$tabla_m} m
			INNER JOIN {$tabla_r} r ON r.mensaje_id = m.id
			LEFT JOIN {$wpdb->users} u ON u.ID = m.remitente_id
			WHERE r.nucleo_id = %d
			ORDER BY m.creado_en DESC
			LIMIT %d",
			$nucleo_id,
			$limit
		);
		$rows = $wpdb->get_results( $sql );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int, object>
	 */
	public static function get_mensajes_enviados( int $user_id, int $limit = 30 ): array {
		global $wpdb;
		$limit   = max( 1, min( 100, $limit ) );
		$tabla_m = $wpdb->prefix . 'ed_mensajes';
		$tabla_r = $wpdb->prefix . 'ed_msg_recepciones';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$sql = $wpdb->prepare(
			"SELECT m.*, COUNT(r.id) AS total_receptores, COALESCE(SUM(r.leido), 0) AS total_leidos
			FROM {$tabla_m} m
			LEFT JOIN {$tabla_r} r ON r.mensaje_id = m.id
			WHERE m.remitente_id = %d
			GROUP BY m.id
			ORDER BY m.creado_en DESC
			LIMIT %d",
			$user_id,
			$limit
		);
		$rows = $wpdb->get_results( $sql );
		return is_array( $rows ) ? $rows : array();
	}

	public static function marcar_leido( int $mensaje_id, int $nucleo_id ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'ed_msg_recepciones',
			array(
				'leido'    => 1,
				'leido_en' => current_time( 'mysql' ),
			),
			array(
				'mensaje_id' => $mensaje_id,
				'nucleo_id'  => $nucleo_id,
				'leido'      => 0,
			),
			array( '%d', '%s' ),
			array( '%d', '%d', '%d' )
		);
	}

	public static function get_no_leidos( int $nucleo_id ): int {
		global $wpdb;
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}ed_msg_recepciones
				WHERE nucleo_id = %d AND leido = 0",
				$nucleo_id
			)
		);
	}

	/**
	 * Llista títols per selectors (staff).
	 *
	 * @return array<int, array{id:int,title:string}>
	 */
	public static function get_destinos_para_selector( string $tipo ): array {
		$tipo = sanitize_key( $tipo );
		$pt   = '';
		switch ( $tipo ) {
			case 'deporte':
				$pt = 'deporte';
				break;
			case 'categoria':
				$pt = 'categoria';
				break;
			case 'nucleo':
				$pt = 'nucleo_familiar';
				break;
			case 'jugador':
				$pt = 'jugador';
				break;
			default:
				return array();
		}

		$posts = get_posts(
			array(
				'post_type'      => $pt,
				'post_status'    => 'publish',
				'posts_per_page' => 200,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);
		$out = array();
		foreach ( $posts as $p ) {
			$out[] = array(
				'id'    => (int) $p->ID,
				'title' => get_the_title( $p ),
			);
		}
		return $out;
	}

	/**
	 * Procesa la cola de emails en lotes de 20 para evitar timeout (504).
	 */
	public static function procesar_cola_emails(): void {
		global $wpdb;

		$tabla_r = $wpdb->prefix . 'ed_msg_recepciones';
		$tabla_m = $wpdb->prefix . 'ed_mensajes';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$pendientes = $wpdb->get_results(
			"SELECT r.id AS recepcion_id, r.nucleo_id, m.asunto, m.cuerpo, m.remitente_id 
			FROM {$tabla_r} r
			INNER JOIN {$tabla_m} m ON m.id = r.mensaje_id
			WHERE r.email_enviado = 0
			ORDER BY r.id ASC
			LIMIT 20"
		);

		if ( empty( $pendientes ) ) {
			return;
		}

		foreach ( $pendientes as $p ) {
			self::enviar_email_nucleo( (int) $p->nucleo_id, (string) $p->asunto, (string) $p->cuerpo, (int) $p->remitente_id );
			$wpdb->update(
				$tabla_r,
				array( 'email_enviado' => 1 ),
				array( 'id' => (int) $p->recepcion_id ),
				array( '%d' ),
				array( '%d' )
			);
		}

		// Si quedan más, reencolamos
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$quedan = (int) $wpdb->get_var( "SELECT COUNT(id) FROM {$tabla_r} WHERE email_enviado = 0" );
		if ( $quedan > 0 ) {
			wp_schedule_single_event( time(), 'ed_cron_procesar_cola_emails' );
		}
	}
}
