<?php
/**
 * Funcions del tema fill escolesesportivesondara.
 *
 * @package escolesesportivesondara
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'ESCOLESESPORTIVESONDARA_VERSION' ) ) {
	define( 'ESCOLESESPORTIVESONDARA_VERSION', wp_get_theme()->get( 'Version' ) );
}

if ( ! defined( 'ESCOLESESPORTIVESONDARA_DIR' ) ) {
	define( 'ESCOLESESPORTIVESONDARA_DIR', trailingslashit( get_stylesheet_directory() ) );
}

if ( ! defined( 'ESCOLESESPORTIVESONDARA_URI' ) ) {
	define( 'ESCOLESESPORTIVESONDARA_URI', trailingslashit( get_stylesheet_directory_uri() ) );
}

/**
 * Configuració base del tema fill.
 */
function escolesesportivesondara_theme_setup() {
	load_child_theme_textdomain( 'escolesesportivesondara', ESCOLESESPORTIVESONDARA_DIR . 'languages' );

	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/global.css' );
}
add_action( 'after_setup_theme', 'escolesesportivesondara_theme_setup' );

/**
 * Retorna una versió basada en el fitxer per evitar problemes de caché.
 */
function escolesesportivesondara_asset_version( $relative_path ) {
	$file_path = ESCOLESESPORTIVESONDARA_DIR . ltrim( $relative_path, '/' );

	if ( file_exists( $file_path ) ) {
		$modified_time = filemtime( $file_path );

		if ( false !== $modified_time ) {
			return (string) $modified_time;
		}
	}

	return ESCOLESESPORTIVESONDARA_VERSION;
}

/**
 * Retorna la URL de Google Fonts del sistema de disseny.
 *
 * @return string
 */
function escolesesportivesondara_fonts_url() {
	$families = array(
		'Barlow Condensed:wght@500;600',
		'Inter:wght@400;600',
		'Oswald:wght@500;600;700',
		'Roboto Mono:wght@400;600',
	);

	$query_args = array(
		'family'  => implode( '&family=', $families ),
		'display' => 'swap',
	);

	return add_query_arg( $query_args, 'https://fonts.googleapis.com/css2' );
}

/**
 * Carrega els estils del tema pare i els assets propis del tema fill.
 */
function escolesesportivesondara_enqueue_styles() {
	$parent = wp_get_theme( get_template() );

	wp_enqueue_style(
		'astra-parent-style',
		trailingslashit( get_template_directory_uri() ) . 'style.css',
		array(),
		$parent->exists() ? $parent->get( 'Version' ) : null
	);

	wp_enqueue_style(
		'escolesesportivesondara-global',
		ESCOLESESPORTIVESONDARA_URI . 'assets/global.css',
		array( 'astra-parent-style' ),
		escolesesportivesondara_asset_version( 'assets/global.css' )
	);
}
add_action( 'wp_enqueue_scripts', 'escolesesportivesondara_enqueue_styles', 15 );

/**
 * Tipografies del sistema de disseny (Google Fonts, display swap).
 */
function escolesesportivesondara_enqueue_fonts() {
	wp_enqueue_style(
		'escolesesportivesondara-fonts',
		escolesesportivesondara_fonts_url(),
		array(),
		null
	);
}
add_action( 'wp_enqueue_scripts', 'escolesesportivesondara_enqueue_fonts', 5 );

/**
 * Afig preconnect per reduir la latència en la càrrega de Google Fonts.
 *
 * @param string[] $urls          URLs ja registrades.
 * @param string   $relation_type Tipus de resource hint.
 * @return string[]
 */
function escolesesportivesondara_resource_hints( $urls, $relation_type ) {
	if ( 'preconnect' !== $relation_type || ! wp_style_is( 'escolesesportivesondara-fonts', 'queue' ) ) {
		return $urls;
	}

	$urls[] = 'https://fonts.googleapis.com';
	$urls[] = array(
		'href'        => 'https://fonts.gstatic.com',
		'crossorigin' => 'anonymous',
	);

	return $urls;
}
add_filter( 'wp_resource_hints', 'escolesesportivesondara_resource_hints', 10, 2 );

/**
 * Redueix codi innecessari al frontend.
 */
function escolesesportivesondara_optimize_frontend() {
	if ( is_admin() ) {
		return;
	}

	remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
	remove_action( 'wp_print_styles', 'print_emoji_styles' );
	remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
	remove_action( 'wp_head', 'rest_output_link_wp_head' );
	remove_action( 'wp_head', 'rsd_link' );
	remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
	remove_action( 'wp_head', 'wlwmanifest_link' );

	add_filter( 'emoji_svg_url', '__return_false' );
}
add_action( 'init', 'escolesesportivesondara_optimize_frontend' );
