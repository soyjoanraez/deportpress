<?php
/**
 * URLs /torneo/{slug}/ i /torneo/.../partido/.../
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reescriptura i plantilles públiques torneig/partit.
 */
class ED_URL_Torneo {

	public static function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite_rules' ), 6 );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'template_redirect', array( self::class, 'handle_template' ), 5 );
	}

	public static function add_rewrite_rules(): void {
		add_rewrite_rule(
			'^resultados/?$',
			'index.php?ed_pagina=resultados',
			'top'
		);
		add_rewrite_rule(
			'^equipo/([^/]+)/?$',
			'index.php?ed_equipo_slug=$matches[1]',
			'top'
		);
		add_rewrite_rule(
			'^torneo/([^/]+)/partido/([^/]+)-vs-([^/]+)/?$',
			'index.php?ed_torneo_slug=$matches[1]&ed_local_slug=$matches[2]&ed_visitante_slug=$matches[3]',
			'top'
		);
		add_rewrite_rule(
			'^torneo/([^/]+)/?$',
			'index.php?ed_torneo_slug=$matches[1]',
			'top'
		);
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'ed_pagina';
		$vars[] = 'ed_equipo_slug';
		$vars[] = 'ed_torneo_slug';
		$vars[] = 'ed_local_slug';
		$vars[] = 'ed_visitante_slug';
		return $vars;
	}

	public static function handle_template(): void {
		$pagina = (string) get_query_var( 'ed_pagina' );
		if ( 'resultados' === $pagina ) {
			self::enqueue_calendario_assets();
			self::load_template( 'resultados' );
			exit;
		}

		$equipo_slug = (string) get_query_var( 'ed_equipo_slug' );
		if ( '' !== $equipo_slug ) {
			$equipo_id = self::find_post_id_by_slug( $equipo_slug, 'equipo' );
			if ( $equipo_id ) {
				if ( ! defined( 'ED_CURRENT_EQUIPO_ID' ) ) {
					define( 'ED_CURRENT_EQUIPO_ID', $equipo_id );
				}
				self::enqueue_calendario_assets();
				self::load_template( 'equipo' );
				exit;
			}
			status_header( 404 );
			nocache_headers();
			wp_die( esc_html__( 'Equip no trobat.', 'escuela-deportiva-core' ), '', array( 'response' => 404 ) );
		}

		$torneo_slug = (string) get_query_var( 'ed_torneo_slug' );
		if ( '' === $torneo_slug ) {
			return;
		}
		$local_slug     = (string) get_query_var( 'ed_local_slug' );
		$visitante_slug = (string) get_query_var( 'ed_visitante_slug' );

		if ( $local_slug && $visitante_slug ) {
			$partido_id = self::find_partido_id( $torneo_slug, $local_slug, $visitante_slug );
			if ( $partido_id ) {
				if ( ! defined( 'ED_CURRENT_PARTIDO_ID' ) ) {
					define( 'ED_CURRENT_PARTIDO_ID', $partido_id );
				}
				self::load_template( 'partido-live' );
				exit;
			}
		} else {
			$torneo_id = self::find_torneo_id( $torneo_slug );
			if ( $torneo_id ) {
				if ( ! defined( 'ED_CURRENT_TORNEO_ID' ) ) {
					define( 'ED_CURRENT_TORNEO_ID', $torneo_id );
				}
				self::load_template( 'torneo' );
				exit;
			}
		}
	}

	private static function find_post_id_by_slug( string $slug, string $post_type ): int {
		$q = new WP_Query(
			array(
				'post_type'      => $post_type,
				'name'           => $slug,
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'fields'         => 'ids',
			)
		);
		return $q->posts ? (int) $q->posts[0] : 0;
	}

	private static function find_partido_id( string $torneo_slug, string $local_slug, string $visitante_slug ): int {
		$torneo_id = self::find_post_id_by_slug( $torneo_slug, 'torneo' );
		$local_id  = self::find_post_id_by_slug( $local_slug, 'equipo' );
		$visit_id  = self::find_post_id_by_slug( $visitante_slug, 'equipo' );
		if ( ! $torneo_id || ! $local_id || ! $visit_id ) {
			return 0;
		}
		$partidos = get_posts(
			array(
				'post_type'      => 'partido',
				'posts_per_page' => 1,
				'post_status'    => 'publish',
				'meta_query'     => array(
					'relation' => 'AND',
					array(
						'key'   => ED_Torneos::PAR_TORNEO,
						'value' => $torneo_id,
					),
					array(
						'key'   => ED_Torneos::PAR_EQ_LOCAL,
						'value' => $local_id,
					),
					array(
						'key'   => ED_Torneos::PAR_EQ_VISITANTE,
						'value' => $visit_id,
					),
				),
				'fields'         => 'ids',
			)
		);
		return $partidos ? (int) $partidos[0] : 0;
	}

	private static function find_torneo_id( string $slug ): int {
		return self::find_post_id_by_slug( $slug, 'torneo' );
	}

	private static function enqueue_calendario_assets(): void {
		wp_enqueue_style(
			'ed-calendario-fase8',
			ED_PLUGIN_URL . 'assets/css/calendario-fase8.css',
			array(),
			ED_VERSION
		);
	}

	private static function load_template( string $name ): void {
		$paths = array(
			get_stylesheet_directory() . '/templates/ed-' . $name . '.php',
			get_template_directory() . '/templates/ed-' . $name . '.php',
			ED_PLUGIN_DIR . 'templates/' . $name . '.php',
		);
		foreach ( $paths as $file ) {
			if ( is_readable( $file ) ) {
				if ( 'equipo' === $name && defined( 'ED_CURRENT_EQUIPO_ID' ) ) {
					global $post;
					// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$post = get_post( (int) ED_CURRENT_EQUIPO_ID );
					if ( $post ) {
						setup_postdata( $post );
					}
				}
				if ( 'partido-live' === $name && defined( 'ED_CURRENT_PARTIDO_ID' ) ) {
					$pid = (int) constant( 'ED_CURRENT_PARTIDO_ID' );
					wp_enqueue_style(
						'ed-directorio',
						ED_PLUGIN_URL . 'assets/css/directorio.css',
						array(),
						ED_VERSION
					);
					wp_enqueue_style(
						'ed-partido-live',
						ED_PLUGIN_URL . 'assets/css/partido-live.css',
						array( 'ed-directorio' ),
						ED_VERSION
					);
					wp_enqueue_script(
						'ed-partido-live',
						ED_PLUGIN_URL . 'assets/js/partido-live.js',
						array(),
						ED_VERSION,
						true
					);
					$torneo_id = 0;
					if ( function_exists( 'get_field' ) ) {
						$torneo_id = (int) get_field( ED_Torneos::PAR_TORNEO, $pid, false );
					}
					wp_localize_script(
						'ed-partido-live',
						'edPartidoLive',
						array(
							'partidoId' => $pid,
							'torneoId'  => $torneo_id,
							'restBase'  => esc_url_raw( rest_url( 'ed/v1' ) ),
							'i18nFollow'=> __( 'Seguir aquest partit', 'escuela-deportiva-core' ),
							'i18nFollowing' => __( 'Seguint aquest partit', 'escuela-deportiva-core' ),
						)
					);
				}
				if ( 'torneo' === $name ) {
					wp_enqueue_style(
						'ed-fase6',
						ED_PLUGIN_URL . 'assets/css/fase6.css',
						array(),
						ED_VERSION
					);
					wp_enqueue_style(
						'ed-torneo-public',
						ED_PLUGIN_URL . 'assets/css/torneo-public.css',
						array( 'ed-fase6' ),
						ED_VERSION
					);
				}
				get_header();
				include $file;
				if ( 'equipo' === $name ) {
					wp_reset_postdata();
				}
				get_footer();
				return;
			}
		}
		status_header( 404 );
		nocache_headers();
		wp_die( esc_html__( 'Plantilla no trobada.', 'escuela-deportiva-core' ), '', array( 'response' => 404 ) );
	}
}
