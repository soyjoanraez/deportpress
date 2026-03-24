<?php
/**
 * Menú d’administració Escuela Deportiva.
 *
 * @package escuela-deportiva-core
 */

defined( 'ABSPATH' ) || exit;

/**
 * Agrupa els CPT sota un menú principal.
 */
class ED_Admin_Menu {

	/**
	 * Hooks.
	 */
	/**
	 * SRI hash per Chart.js 4.4.1 (cdn.jsdelivr.net).
	 * Actualitzar si es canvia la versió.
	 */
	private const CHARTJS_SRI = 'sha256-+6sxrQLBoBiK+Fl4GfRGJFIQnRlS4K8p1Oix5m4nPs=';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'register_menu' ), 9 );
		add_action( 'admin_post_ed_ffcv_sync_manual', array( $this, 'handle_ffcv_sync_manual' ) );
		add_action( 'admin_post_ed_ffcv_limpiar_cache', array( $this, 'handle_ffcv_limpiar_cache' ) );
		add_action( 'admin_post_ed_federacion_guardar', array( $this, 'handle_federacion_guardar' ) );
		add_action( 'admin_post_ed_federacion_mapeo_add', array( $this, 'handle_federacion_mapeo_add' ) );
		add_action( 'admin_post_ed_federacion_mapeo_borrar', array( $this, 'handle_federacion_mapeo_borrar' ) );
		add_filter( 'script_loader_tag', array( $this, 'add_chartjs_sri' ), 10, 2 );
	}

	/**
	 * Afegeix atributs integrity i crossorigin a Chart.js per protecció SRI.
	 */
	public function add_chartjs_sri( string $tag, string $handle ): string {
		if ( 'chart-js' !== $handle ) {
			return $tag;
		}
		return str_replace(
			'<script ',
			'<script integrity="' . self::CHARTJS_SRI . '" crossorigin="anonymous" ',
			$tag
		);
	}

	/**
	 * Crea el menú i els enllaços als CPT.
	 */
	public function register_menu(): void {
		add_menu_page(
			__( 'Escola esportiva', 'escuela-deportiva-core' ),
			__( 'Escola esportiva', 'escuela-deportiva-core' ),
			'edit_jugadores',
			'ed-core',
			array( $this, 'render_hub' ),
			'dashicons-groups',
			58
		);

		$items = array(
			array( 'slug' => 'nucleo_familiar', 'cap' => 'edit_nucleo_familiars' ),
			array( 'slug' => 'jugador', 'cap' => 'edit_jugadores' ),
			array( 'slug' => 'deporte', 'cap' => 'edit_deportes' ),
			array( 'slug' => 'categoria', 'cap' => 'edit_categorias' ),
			array( 'slug' => 'equipo', 'cap' => 'edit_equipos' ),
			array( 'slug' => 'entrenamiento', 'cap' => 'edit_entrenamientos' ),
			array( 'slug' => 'torneo', 'cap' => 'edit_torneos' ),
			array( 'slug' => 'partido', 'cap' => 'edit_partidos' ),
		);

		if ( ED_Dashboard_Admin::current_user_can_view_dashboard() ) {
			add_submenu_page(
				'ed-core',
				__( 'Dashboard', 'escuela-deportiva-core' ),
				__( 'Dashboard', 'escuela-deportiva-core' ),
				'manage_escuela_deportiva',
				'ed-dashboard',
				array( $this, 'render_dashboard' )
			);
		}

		foreach ( $items as $item ) {
			$pto = get_post_type_object( $item['slug'] );
			if ( ! $pto ) {
				continue;
			}
			add_submenu_page(
				'ed-core',
				$pto->labels->name,
				$pto->labels->menu_name,
				$item['cap'],
				'edit.php?post_type=' . $item['slug']
			);
		}

		foreach ( array( 'anuncio', 'empresa_directorio' ) as $ed_pt ) {
			$pto = get_post_type_object( $ed_pt );
			if ( ! $pto ) {
				continue;
			}
			add_submenu_page(
				'ed-core',
				$pto->labels->name,
				$pto->labels->menu_name,
				$pto->cap->edit_posts,
				'edit.php?post_type=' . $ed_pt
			);
		}

		add_submenu_page(
			'ed-core',
			__( 'Estadístiques publicitat', 'escuela-deportiva-core' ),
			__( 'Stats publicitat', 'escuela-deportiva-core' ),
			'manage_escuela_deportiva',
			'ed-pub-stats',
			array( $this, 'render_pub_stats' )
		);

		add_submenu_page(
			'ed-core',
			__( 'Cròniques IA', 'escuela-deportiva-core' ),
			__( 'Cròniques IA', 'escuela-deportiva-core' ),
			'manage_escuela_deportiva',
			'ed-cronicas',
			array( $this, 'render_cronicas' )
		);

		add_submenu_page(
			'ed-core',
			__( 'FFCV / Federació', 'escuela-deportiva-core' ),
			__( 'FFCV', 'escuela-deportiva-core' ),
			'manage_escuela_deportiva',
			'ed-federacion',
			array( $this, 'render_federacion' )
		);

		remove_submenu_page( 'ed-core', 'ed-core' );
	}

	/**
	 * Pàgina inicial del menú: dashboard de mètriques.
	 */
	public function render_hub(): void {
		if ( ED_Dashboard_Admin::current_user_can_view_dashboard() ) {
			wp_safe_redirect( admin_url( 'admin.php?page=ed-dashboard' ) );
		} else {
			wp_safe_redirect( admin_url( 'edit.php?post_type=jugador' ) );
		}
		exit;
	}

	public function render_dashboard(): void {
		if ( ! ED_Dashboard_Admin::current_user_can_view_dashboard() ) {
			wp_die( esc_html__( 'No autoritzat.', 'escuela-deportiva-core' ) );
		}

		$chart_ver = '4.4.1';
		wp_enqueue_script(
			'chart-js',
			'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js',
			array(),
			$chart_ver,
			true
		);
		wp_enqueue_script(
			'ed-admin-dashboard',
			ED_PLUGIN_URL . 'assets/js/admin-dashboard.js',
			array( 'chart-js' ),
			ED_VERSION,
			true
		);
		wp_localize_script(
			'ed-admin-dashboard',
			'ED_Dashboard',
			array(
				'apiUrl' => esc_url_raw( rest_url( 'ed/v1' ) ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'i18n'   => array(
					'title'           => __( 'Dashboard — Escoles Esportives', 'escuela-deportiva-core' ),
					'loading'         => __( 'Carregant…', 'escuela-deportiva-core' ),
					'updated'         => __( 'Última actualització:', 'escuela-deportiva-core' ),
					'kpiJugadores'    => __( 'Jugadors actius', 'escuela-deportiva-core' ),
					'kpiIngresosMes'  => __( 'Ingressos aquest mes', 'escuela-deportiva-core' ),
					'kpiPagosPct'     => __( 'Pagaments completats', 'escuela-deportiva-core' ),
					'kpiPagosSub'     => __( 'plazos totals', 'escuela-deportiva-core' ),
					'kpiPendientes'   => __( 'Plazos pendents', 'escuela-deportiva-core' ),
					'kpiFallidos'     => __( 'Impagats / fallits', 'escuela-deportiva-core' ),
					'kpiAsistencia'   => __( 'Assistència mitjana', 'escuela-deportiva-core' ),
					'kpiAsistSub'     => __( 'Últims 30 dies', 'escuela-deportiva-core' ),
					'kpiMensajes'     => __( 'Missatges sense llegir', 'escuela-deportiva-core' ),
					'kpiTorneos'      => __( 'Tornejos actius', 'escuela-deportiva-core' ),
					'chartIngresos'   => __( 'Ingressos per mes (€)', 'escuela-deportiva-core' ),
					'chartDeportes'   => __( 'Inscripcions per esport', 'escuela-deportiva-core' ),
					'chartAsistencia' => __( '% Assistència per categoria (30 dies)', 'escuela-deportiva-core' ),
					'alertImpagos'    => __( 'Impagats i pagaments fallits', 'escuela-deportiva-core' ),
					'alertBaja'       => __( 'Baixa assistència (< 60%)', 'escuela-deportiva-core' ),
					'exportCsv'       => __( 'Exportar CSV', 'escuela-deportiva-core' ),
					'exportsTitle'    => __( 'Exportar dades', 'escuela-deportiva-core' ),
					'expJugadores'    => __( 'Jugadors', 'escuela-deportiva-core' ),
					'expPagos'        => __( 'Pagaments i plazos', 'escuela-deportiva-core' ),
					'expAsist'        => __( 'Assistències', 'escuela-deportiva-core' ),
					'expImpagos'      => __( 'Impagats', 'escuela-deportiva-core' ),
					'expBaja'         => __( 'Baixa assistència', 'escuela-deportiva-core' ),
					'tablaEmptyImp'   => __( 'Sense impagats registrats.', 'escuela-deportiva-core' ),
					'tablaEmptyBaja'  => __( 'Tots els jugadors tenen bona assistència.', 'escuela-deportiva-core' ),
					'thJugador'       => __( 'Jugador', 'escuela-deportiva-core' ),
					'thDeporte'       => __( 'Esport', 'escuela-deportiva-core' ),
					'thImporte'       => __( 'Import', 'escuela-deportiva-core' ),
					'thFecha'         => __( 'Data prevista', 'escuela-deportiva-core' ),
					'thEstado'        => __( 'Estat', 'escuela-deportiva-core' ),
					'thSesiones'      => __( 'Sessions', 'escuela-deportiva-core' ),
					'thAsist'         => __( 'Assistències', 'escuela-deportiva-core' ),
					'thPct'           => __( '%', 'escuela-deportiva-core' ),
					'datasetIngresos' => __( 'Ingressos (€)', 'escuela-deportiva-core' ),
					'datasetAsistPct' => __( '% Assistència', 'escuela-deportiva-core' ),
				),
			)
		);
		wp_enqueue_style(
			'ed-admin-dashboard',
			ED_PLUGIN_URL . 'assets/css/admin-dashboard.css',
			array(),
			ED_VERSION
		);

		require ED_PLUGIN_DIR . 'admin/views/dashboard.php';
	}

	public function render_pub_stats(): void {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_escuela_deportiva' ) ) {
			wp_die( esc_html__( 'No autoritzat.', 'escuela-deportiva-core' ) );
		}
		require ED_PLUGIN_DIR . 'admin/views/pub-stats.php';
	}

	public function render_cronicas(): void {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_escuela_deportiva' ) ) {
			wp_die( esc_html__( 'No autoritzat.', 'escuela-deportiva-core' ) );
		}
		require ED_PLUGIN_DIR . 'admin/views/cronicas.php';
	}

	public function render_federacion(): void {
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_escuela_deportiva' ) ) {
			wp_die( esc_html__( 'No autoritzat.', 'escuela-deportiva-core' ) );
		}
		require ED_PLUGIN_DIR . 'admin/views/federacion.php';
	}

	public function handle_ffcv_sync_manual(): void {
		check_admin_referer( 'ed_ffcv_sync' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_escuela_deportiva' ) ) {
			wp_die( esc_html__( 'No autoritzat.', 'escuela-deportiva-core' ) );
		}
		if ( class_exists( 'ED_Sync_Federacion' ) ) {
			ED_Sync_Federacion::sincronizar( true );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=ed-federacion&synced=1' ) );
		exit;
	}

	public function handle_ffcv_limpiar_cache(): void {
		check_admin_referer( 'ed_ffcv_cache' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_escuela_deportiva' ) ) {
			wp_die( esc_html__( 'No autoritzat.', 'escuela-deportiva-core' ) );
		}
		ED_Scraper_FFCV::limpiar_cache();
		wp_safe_redirect( admin_url( 'admin.php?page=ed-federacion&cache=1' ) );
		exit;
	}

	public function handle_federacion_guardar(): void {
		check_admin_referer( 'ed_federacion_guardar' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_escuela_deportiva' ) ) {
			wp_die( esc_html__( 'No autoritzat.', 'escuela-deportiva-core' ) );
		}

		$club = sanitize_text_field( wp_unslash( $_POST['ed_nombre_club'] ?? '' ) );
		update_option( 'ed_nombre_club', $club );

		$temp = sanitize_text_field( wp_unslash( $_POST['ed_temporada_actual'] ?? '2025-26' ) );
		update_option( 'ed_temporada_actual', $temp );

		$json_raw = isset( $_POST['ed_ffcv_categorias_json'] ) ? wp_unslash( $_POST['ed_ffcv_categorias_json'] ) : '[]';
		$decoded  = json_decode( $json_raw, true );
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		ED_Sync_Federacion::save_config(
			array(
				'activa'     => ! empty( $_POST['ed_ffcv_activa'] ),
				'categorias' => $decoded,
			)
		);

		wp_safe_redirect( admin_url( 'admin.php?page=ed-federacion&updated=1' ) );
		exit;
	}

	public function handle_federacion_mapeo_add(): void {
		check_admin_referer( 'ed_federacion_mapeo_add' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_escuela_deportiva' ) ) {
			wp_die( esc_html__( 'No autoritzat.', 'escuela-deportiva-core' ) );
		}

		global $wpdb;
		$cat_ff = sanitize_text_field( wp_unslash( $_POST['categoria_ffcv'] ?? '' ) );
		$comp   = sanitize_text_field( wp_unslash( $_POST['competicion'] ?? '' ) );
		$cid    = (int) ( $_POST['categoria_id'] ?? 0 );

		if ( $cat_ff && $cid > 0 ) {
			$wpdb->replace(
				$wpdb->prefix . 'ed_fed_mapeo',
				array(
					'categoria_ffcv' => $cat_ff,
					'competicion'    => $comp,
					'categoria_id'   => $cid,
				),
				array( '%s', '%s', '%d' )
			);
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ed-federacion&updated=1' ) );
		exit;
	}

	public function handle_federacion_mapeo_borrar(): void {
		check_admin_referer( 'ed_federacion_mapeo_borrar' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_escuela_deportiva' ) ) {
			wp_die( esc_html__( 'No autoritzat.', 'escuela-deportiva-core' ) );
		}

		$id = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		if ( $id > 0 ) {
			global $wpdb;
			$wpdb->delete( $wpdb->prefix . 'ed_fed_mapeo', array( 'id' => $id ), array( '%d' ) );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=ed-federacion&updated=1' ) );
		exit;
	}
}
