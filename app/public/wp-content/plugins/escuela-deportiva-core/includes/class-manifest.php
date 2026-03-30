<?php
/**
 * Manifest PWA i regles d’URL auxiliars.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Serve manifest.json i registra rewrite.
 */
class ED_Manifest {

	public static function register(): void {
		add_action( 'init', array( self::class, 'add_rewrite' ), 5 );
		add_filter( 'query_vars', array( self::class, 'query_vars' ) );
		add_action( 'template_redirect', array( self::class, 'maybe_serve_manifest' ), 1 );
		add_action( 'wp_head', array( self::class, 'print_manifest_link' ), 2 );
	}

	public static function print_manifest_link(): void {
		if ( is_admin() ) {
			return;
		}
		printf(
			'<link rel="manifest" href="%s" />' . "\n",
			esc_url( home_url( '/manifest.json' ) )
		);
	}

	public static function add_rewrite(): void {
		add_rewrite_rule( '^manifest\.json$', 'index.php?ed_manifest=1', 'top' );
	}

	/**
	 * @param string[] $vars
	 * @return string[]
	 */
	public static function query_vars( array $vars ): array {
		$vars[] = 'ed_manifest';
		return $vars;
	}

	public static function maybe_serve_manifest(): void {
		if ( ! (int) get_query_var( 'ed_manifest' ) ) {
			return;
		}
		$icon192 = get_stylesheet_directory_uri() . '/assets/icons/icon-192.png';
		$icon512 = get_stylesheet_directory_uri() . '/assets/icons/icon-512.png';
		$manifest = array(
			'name'             => get_bloginfo( 'name' ),
			'short_name'       => 'Ondara',
			'description'      => get_bloginfo( 'description' ),
			'start_url'        => home_url( '/' ),
			'display'          => 'standalone',
			'background_color' => '#1A1A1A',
			'theme_color'      => '#FFD600',
			'icons'            => array(
				array(
					'src'   => $icon192,
					'sizes' => '192x192',
					'type'  => 'image/png',
				),
				array(
					'src'   => $icon512,
					'sizes' => '512x512',
					'type'  => 'image/png',
				),
			),
		);
		header( 'Content-Type: application/manifest+json; charset=utf-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo wp_json_encode( $manifest );
		exit;
	}
}
