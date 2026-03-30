<?php
/**
 * Publicitat per posicions (Fase 5).
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Banners i estadístiques d’impressions/clicks.
 */
class ED_Publicidad {

	public const POSICIONES = array(
		'home_top'       => array(
			'label' => 'Inici — Banner superior',
			'size'  => '728x90',
		),
		'home_sidebar'   => array(
			'label' => 'Inici — Sidebar',
			'size'  => '300x250',
		),
		'resultados_top' => array(
			'label' => 'Resultats — Superior',
			'size'  => '728x90',
		),
		'partido_top'    => array(
			'label' => 'Partit en viu — Superior',
			'size'  => '728x90',
		),
		'partido_bottom' => array(
			'label' => 'Partit en viu — Inferior',
			'size'  => '300x250',
		),
		'directorio_top' => array(
			'label' => 'Directori — Banner superior',
			'size'  => '728x90',
		),
	);

	public static function register(): void {
		add_action( 'save_post_anuncio', array( self::class, 'on_save_anuncio' ), 20, 3 );
		add_action( 'wp_enqueue_scripts', array( self::class, 'enqueue_track_script' ), 30 );
		add_action( 'woocommerce_subscription_status_updated', array( self::class, 'on_subscription_status' ), 10, 3 );
	}

	/**
	 * @return array<string, string>
	 */
	public static function posicion_choices(): array {
		$out = array();
		foreach ( self::POSICIONES as $key => $cfg ) {
			$out[ $key ] = $cfg['label'];
		}
		return $out;
	}

	public static function posicion_valida( string $pos ): bool {
		return isset( self::POSICIONES[ $pos ] );
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public static function get_anuncios( string $posicion ): array {
		if ( ! self::posicion_valida( $posicion ) ) {
			return array();
		}
		$cache_key = 'ed_pub_pos_' . $posicion;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$posts = get_posts(
			array(
				'post_type'      => 'anuncio',
				'posts_per_page' => -1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => 'ed_anuncio_posicion',
						'value' => $posicion,
					),
					array(
						'key'   => 'ed_anuncio_activo',
						'value' => '1',
					),
				),
			)
		);

		$anuncios = array();
		foreach ( $posts as $p ) {
			if ( ! function_exists( 'get_field' ) ) {
				continue;
			}
			$imagen_id = get_field( 'ed_anuncio_imagen', $p->ID );
			$anuncios[] = array(
				'id'          => $p->ID,
				'empresa'     => (string) get_field( 'ed_anuncio_empresa_nombre', $p->ID ),
				'imagen_url'  => $imagen_id ? wp_get_attachment_image_url( (int) $imagen_id, 'full' ) : null,
				'url_destino' => (string) get_field( 'ed_anuncio_url_destino', $p->ID ),
				'target'      => '_blank',
			);
		}
		shuffle( $anuncios );
		set_transient( $cache_key, $anuncios, 5 * MINUTE_IN_SECONDS );
		return $anuncios;
	}

	public static function render( string $posicion, int $max = 1 ): string {
		$anuncios = self::get_anuncios( $posicion );
		if ( empty( $anuncios ) ) {
			return '';
		}

		$html = '<div class="ed-pub-zona ed-pub-zona--' . esc_attr( $posicion ) . '">';
		foreach ( array_slice( $anuncios, 0, $max ) as $a ) {
			if ( empty( $a['imagen_url'] ) ) {
				continue;
			}
			$html .= sprintf(
				'<a href="%s" target="%s" rel="noopener nofollow sponsored" class="ed-pub-banner" data-anuncio-id="%d" onclick="if(typeof edTrackClick===\'function\'){edTrackClick(%d);}">',
				esc_url( $a['url_destino'] ),
				esc_attr( $a['target'] ),
				(int) $a['id'],
				(int) $a['id']
			);
			$html .= sprintf(
				'<img src="%s" alt="%s" loading="lazy" width="728" height="90" />',
				esc_url( $a['imagen_url'] ),
				esc_attr( $a['empresa'] )
			);
			$html .= '</a>';
			self::registrar_evento( (int) $a['id'], 'impresion' );
		}
		$html .= '</div>';
		return $html;
	}

	public static function registrar_evento( int $anuncio_id, string $tipo ): void {
		if ( ! in_array( $tipo, array( 'impresion', 'click' ), true ) ) {
			return;
		}
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'ed_pub_impresiones',
			array(
				'anuncio_id' => $anuncio_id,
				'tipo'       => $tipo,
				'fecha'      => gmdate( 'Y-m-d' ),
			),
			array( '%d', '%s', '%s' )
		);
	}

	/**
	 * @return object[]
	 */
	public static function get_estadisticas( int $anuncio_id, int $dias = 30 ): array {
		global $wpdb;
		$desde = gmdate( 'Y-m-d', strtotime( '-' . $dias . ' days' ) );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT tipo, fecha, COUNT(*) AS total
				FROM {$wpdb->prefix}ed_pub_impresiones
				WHERE anuncio_id = %d AND fecha >= %s
				GROUP BY tipo, fecha
				ORDER BY fecha ASC",
				$anuncio_id,
				$desde
			)
		);
	}

	public static function invalidate_all_positions(): void {
		foreach ( array_keys( self::POSICIONES ) as $pos ) {
			delete_transient( 'ed_pub_pos_' . $pos );
		}
	}

	/**
	 * @param int|\WP_Post $post_id Post ID o objecte.
	 */
	public static function on_save_anuncio( $post_id, $post = null, $update = null ): void {
		unset( $post, $update );
		$post_id = (int) $post_id;
		if ( $post_id <= 0 || wp_is_post_revision( $post_id ) ) {
			return;
		}
		if ( function_exists( 'get_field' ) ) {
			$pos = (string) get_field( 'ed_anuncio_posicion', $post_id );
			if ( $pos && isset( self::POSICIONES[ $pos ] ) ) {
				delete_transient( 'ed_pub_pos_' . $pos );
			}
		}
		self::invalidate_all_positions();
	}

	/**
	 * WooCommerce Subscriptions (opcional).
	 *
	 * @param mixed $subscription Objecte subscripció.
	 */
	public static function on_subscription_status( $subscription, string $new_status, string $old_status ): void {
		unset( $old_status );
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_meta' ) ) {
			return;
		}
		$anuncio_id = (int) $subscription->get_meta( '_ed_anuncio_id' );
		if ( ! $anuncio_id || ! function_exists( 'update_field' ) ) {
			return;
		}
		$activo = in_array( $new_status, array( 'active', 'pending-cancel' ), true ) ? 1 : 0;
		update_field( 'ed_anuncio_activo', $activo, $anuncio_id );
		self::invalidate_all_positions();
	}

	public static function enqueue_track_script(): void {
		if ( is_admin() ) {
			return;
		}
		wp_register_script(
			'ed-pub-track',
			ED_PLUGIN_URL . 'assets/js/pub-track.js',
			array(),
			ED_VERSION,
			true
		);
		wp_enqueue_script( 'ed-pub-track' );
		wp_add_inline_script(
			'ed-pub-track',
			'window.edPubRest=' . wp_json_encode( esc_url_raw( rest_url( 'ed/v1' ) ) ) . ';',
			'before'
		);
	}
}
