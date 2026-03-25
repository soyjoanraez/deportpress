<?php
/**
 * Bootstrap del plugin.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Carrega classes i registra hooks.
 */
class ED_Loader {

	/**
	 * Instància singleton.
	 *
	 * @var ED_Loader|null
	 */
	private static ?ED_Loader $instance = null;

	/**
	 * Singleton.
	 */
	public static function instance(): ED_Loader {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor privat.
	 */
	private function __construct() {
		$this->load_files();
		add_action( 'ed_plazo_pagado', array( 'ED_Dashboard_Admin', 'invalidar_cache' ), 10, 0 );
		add_action( 'plugins_loaded', array( $this, 'run_db_upgrade' ), 3 );
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ), 5 );
		add_action( 'init', array( $this, 'seed_default_catalog' ), 30 );
		ED_Roles::init();
		$this->register_modules();
	}

	/**
	 * Migracions d'esquema fora de l'hook d'activació.
	 */
	public function run_db_upgrade(): void {
		ED_Activator::maybe_upgrade();
	}

	/**
	 * Require dels fitxers de includes.
	 */
	private function load_files(): void {
		$base = ED_PLUGIN_DIR . 'includes/';
		require_once $base . 'class-roles.php';
		require_once $base . 'class-cpt.php';
		require_once $base . 'class-publicidad.php';
		require_once $base . 'class-directorio.php';
		require_once $base . 'class-ia-cronicas.php';
		require_once $base . 'class-entradas-qr.php';
		require_once $base . 'class-rifa.php';
		require_once $base . 'class-bar.php';
		require_once $base . 'class-ed-scraper-ffcv.php';
		require_once $base . 'class-ed-sync-federacion.php';
		require_once $base . 'class-calendario.php';
		require_once $base . 'class-comunicaciones.php';
		require_once $base . 'class-dashboard-admin.php';
		require_once $base . 'class-acf-fields.php';
		require_once $base . 'class-categoria-manager.php';
		require_once $base . 'class-jugador-nucleo-binding.php';
		require_once $base . 'class-nucleo-repository.php';
		require_once $base . 'class-torneos.php';
		require_once $base . 'class-partidos-eventos.php';
		require_once $base . 'class-rankings.php';
		require_once $base . 'class-push.php';
		require_once $base . 'class-mvp.php';
		require_once $base . 'class-rest-api.php';
		require_once $base . 'class-admin-menu.php';
		require_once $base . 'class-public.php';
		require_once $base . 'class-litespeed.php';
		require_once $base . 'class-woocommerce.php';
		require_once $base . 'class-emails.php';
		require_once $base . 'class-pagos.php';
		require_once $base . 'class-pagos-stripe.php';
		require_once $base . 'class-pagos-redsys.php';
		require_once $base . 'class-asistencias.php';
		require_once $base . 'class-cron.php';
		require_once $base . 'class-manifest.php';
		require_once $base . 'class-url-torneo.php';
	}

	/**
	 * i18n.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'escuela-deportiva-core',
			false,
			dirname( plugin_basename( ED_PLUGIN_FILE ) ) . '/languages/'
		);
	}

	/**
	 * Crea el catàleg base una sola vegada quan els CPT ja estan registrats.
	 */
	public function seed_default_catalog(): void {
		ED_Activator::maybe_seed_default_catalog();
	}

	/**
	 * Inicialitza mòduls.
	 */
	private function register_modules(): void {
		( new ED_CPT() )->register();
		( new ED_ACF_Fields() )->register();
		( new ED_Categoria_Manager() )->register();
		( new ED_Jugador_Nucleo_Binding() )->register();
		( new ED_REST_API() )->register();
		( new ED_Admin_Menu() )->register();
		( new ED_Public() )->register();
		( new ED_LiteSpeed() )->register();
		( new ED_WooCommerce() )->register();
		ED_Publicidad::register();
		ED_Directorio::register();
		ED_IA_Cronicas::register();
		( new ED_Cron() )->register();
		ED_Manifest::register();
		ED_URL_Torneo::register();
		ED_Torneos::register_hooks();
		ED_MVP::register_hooks();
		ED_Push::register_hooks();
	}
}
